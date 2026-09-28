import Foundation
import PhotosUI
import UIKit
import UniformTypeIdentifiers

/// The photo picker, and the request state shared with its delegate.
///
/// The Android half of this plugin does the same job; both exist because
/// NativePHP ships `Native\Mobile\Camera::pickImages()` and
/// `PendingMediaPicker`, both of which call the `Camera.PickMedia` bridge
/// function, but no core implementation of that function exists. The result
/// arrives later as `Events\Gallery\MediaSelected`, never as this call's
/// return value.
///
/// PHPicker is modal and can only have one request in flight, so the pending
/// request lives here rather than travelling with the delegate instance. That
/// is also why there is no token: the picker is the correlation.
enum CameraPicker {

    private static let cacheDirectoryName = "native-media"
    private static let defaultMime = "image/jpeg"
    private static let defaultEvent = "Native\\Mobile\\Events\\Gallery\\MediaSelected"

    private static let lock = NSLock()

    private static var pendingEvent = defaultEvent
    private static var pendingId: String?
    private static var pendingMaxItems = 1

    // MARK: - Request state

    static func configure(maxItems: Int, id: String?, event: String?) {
        lock.lock()
        defer { lock.unlock() }

        pendingMaxItems = maxItems > 0 ? maxItems : 1
        pendingId = id
        pendingEvent = resolvedEvent(event)
    }

    /// The request the next result belongs to.
    ///
    /// Read by both the delegate and the copy that follows it, so it must not
    /// consume the state — the id has to survive from the pick to the event.
    static func takeRequest() -> (event: String, id: String?, maxItems: Int) {
        lock.lock()
        defer { lock.unlock() }

        return (pendingEvent, pendingId, pendingMaxItems)
    }

    static func resolvedEvent(_ event: String?) -> String {
        guard let event = event, !event.isEmpty else {
            return defaultEvent
        }

        return event
    }

    // MARK: - Presentation

    /// The controller to present from: the key window's root, unwound through
    /// anything already presented, so the picker lands on top of whatever the
    /// bridge call happened to interrupt.
    static func topViewController() -> UIViewController? {
        let window = UIApplication.shared.connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .flatMap { $0.windows }
            .first { $0.isKeyWindow }

        var controller = window?.rootViewController

        while let presented = controller?.presentedViewController {
            controller = presented
        }

        return controller
    }

    static func makeConfiguration(mediaType: String, maxItems: Int) -> PHPickerConfiguration {
        let configuration = PHPickerConfiguration(photoLibrary: .shared())

        switch mediaType.lowercased() {
        case "image":
            configuration.filter = .images
        case "video":
            configuration.filter = .videos
        default:
            configuration.filter = .any(of: [.images, .videos])
        }

        // PHPicker treats 0 as unlimited, which is not what an empty
        // `maxItems` should mean; the caller always wants at least one.
        configuration.selectionLimit = max(1, maxItems)

        return configuration
    }

    // MARK: - Results

    /// Copy what the user chose out of the picker and report it to PHP.
    ///
    /// `loadFileRepresentation` hands over a temporary URL that is only valid
    /// inside its own callback, so each file is copied before that callback
    /// returns. The cache is the right home: these stage an upload, and the
    /// system is free to reclaim them afterwards.
    static func loadFiles(from results: [PHPickerResult], maxItems: Int) {
        let group = DispatchGroup()
        let entryLock = NSLock()

        var entries: [[String: Any]] = []

        for result in results.prefix(maxItems) {
            let provider = result.itemProvider

            guard let typeIdentifier = preferredType(for: provider) else {
                continue
            }

            group.enter()

            provider.loadFileRepresentation(forTypeIdentifier: typeIdentifier) { url, _ in
                defer { group.leave() }

                guard
                    let url = url,
                    let entry = copyToCache(url, name: provider.suggestedName, typeIdentifier: typeIdentifier)
                else {
                    return
                }

                entryLock.lock()
                entries.append(entry)
                entryLock.unlock()
            }
        }

        group.notify(queue: .main) {
            let request = takeRequest()

            if entries.isEmpty {
                // An empty selection is a cancel, not a failure, and PHP is
                // told so rather than being left waiting. A selection that
                // produced nothing readable is the one real error here.
                if results.isEmpty {
                    dispatch(
                        event: request.event,
                        payload: [
                            "success": false,
                            "files": [],
                            "count": 0,
                            "cancelled": true,
                        ],
                        id: request.id
                    )
                } else {
                    report(
                        event: request.event,
                        id: request.id,
                        message: "The chosen image could not be read"
                    )
                }

                return
            }

            dispatch(
                event: request.event,
                payload: [
                    "success": true,
                    "files": entries,
                    "count": entries.count,
                    "cancelled": false,
                ],
                id: request.id
            )
        }
    }

    /// An image type the picker actually offered, preferring the formats the
    /// server accepts. GIF matters: the web accepts it, so a GIF must not be
    /// transcoded to a JPEG on its way up.
    private static func preferredType(for provider: NSItemProvider) -> String? {
        let preferred: [UTType] = [.jpeg, .png, .gif, .heic, .webP]

        for type in preferred where provider.hasItemConformingToTypeIdentifier(type.identifier) {
            return type.identifier
        }

        return provider.registeredTypeIdentifiers.first
    }

    private static func copyToCache(
        _ source: URL,
        name: String?,
        typeIdentifier: String
    ) -> [String: Any]? {
        guard
            let caches = FileManager.default.urls(for: .cachesDirectory, in: .userDomainMask).first
        else {
            return nil
        }

        let directory = caches.appendingPathComponent(cacheDirectoryName, isDirectory: true)

        do {
            try FileManager.default.createDirectory(
                at: directory,
                withIntermediateDirectories: true
            )
        } catch {
            return nil
        }

        let type = UTType(typeIdentifier)
        let ext = type?.preferredFilenameExtension ?? "jpg"
        let target = directory.appendingPathComponent("\(UUID().uuidString).\(ext)")

        do {
            try FileManager.default.copyItem(at: source, to: target)
        } catch {
            return nil
        }

        let attributes = try? FileManager.default.attributesOfItem(atPath: target.path)
        let size = (attributes?[.size] as? NSNumber)?.intValue ?? 0

        return [
            "path": target.path,
            "name": name ?? target.lastPathComponent,
            "mime": type?.preferredMIMEType ?? defaultMime,
            "size": size,
        ]
    }

    // MARK: - Events

    /// Report a picker that could not be opened or read at all.
    ///
    /// PHP is already waiting on an event by the time any of this runs, so
    /// swallowing the failure would leave the composer spinning on a pick
    /// that never completes. MediaSelected carries an `error`, so use it.
    static func report(event: String?, id: String?, message: String) {
        var payload: [String: Any] = [
            "success": false,
            "files": [],
            "count": 0,
            "cancelled": false,
            "error": message,
        ]

        if let id = id {
            payload["id"] = id
        }

        dispatch(event: resolvedEvent(event), payload: payload)
    }

    static func dispatch(event: String, payload: [String: Any], id: String? = nil) {
        var body = payload

        if let id = id {
            body["id"] = id
        }

        guard
            let data = try? JSONSerialization.data(withJSONObject: body),
            let json = String(data: data, encoding: .utf8)
        else {
            return
        }

        DispatchQueue.main.async {
            NativeElementBridge.sendNativeEvent(eventName: event, payloadJson: json)
        }
    }
}

/// Receives the picker's result.
///
/// PHPicker hands back file representations rather than images, so nothing
/// has to be decoded — which is what keeps a large selection from costing the
/// memory an `UIImage` round trip would.
final class CameraPickerDelegate: NSObject, PHPickerViewControllerDelegate {

    static let shared = CameraPickerDelegate()

    func picker(_ picker: PHPickerViewController, didFinishPicking results: [PHPickerResult]) {
        picker.dismiss(animated: true)

        let request = CameraPicker.takeRequest()

        if results.isEmpty {
            CameraPicker.dispatch(
                event: request.event,
                payload: [
                    "success": false,
                    "files": [],
                    "count": 0,
                    "cancelled": true,
                ],
                id: request.id
            )

            return
        }

        CameraPicker.loadFiles(from: results, maxItems: request.maxItems)
    }
}
