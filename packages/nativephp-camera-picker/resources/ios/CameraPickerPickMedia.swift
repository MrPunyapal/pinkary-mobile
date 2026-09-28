import Foundation
import PhotosUI
import UIKit

/// Namespace: "Camera.*"
///
/// The bridge entry point behind `Native\Mobile\Camera::pickImages()`.
///
/// A picker is asynchronous by nature, so this returns as soon as the system
/// picker has been handed the request; the chosen files arrive through
/// `Native\Mobile\Events\Gallery\MediaSelected`, not through this return
/// value. That is what `PendingMediaPicker::start()` checks for, hence the
/// explicit `status`.
struct CameraPickerPickMedia: BridgeFunction {

    func execute(parameters: [String: Any]) throws -> [String: Any] {
        let mediaType = parameters["mediaType"] as? String ?? "all"
        let maxItems = (parameters["maxItems"] as? NSNumber)?.intValue ?? 1
        let id = parameters["id"] as? String
        let event = parameters["event"] as? String

        // The bridge call does not arrive on the main thread, and UIKit
        // presentation must happen there.
        DispatchQueue.main.async {
            CameraPicker.configure(maxItems: maxItems, id: id, event: event)

            guard let presenter = CameraPicker.topViewController() else {
                // Already past the return value, so the failure has to reach
                // PHP as the event — otherwise the composer waits forever on a
                // pick that never happens, showing nothing at all.
                CameraPicker.report(
                    event: event,
                    id: id,
                    message: "Could not open the photo picker"
                )

                return
            }

            let picker = PHPickerViewController(
                configuration: CameraPicker.makeConfiguration(
                    mediaType: mediaType,
                    maxItems: maxItems
                )
            )

            picker.delegate = CameraPickerDelegate.shared
            presenter.present(picker, animated: true)
        }

        // status is what PendingMediaPicker::start() checks for. The picker has
        // been handed over, not finished — the result is a later event.
        return ["success": true, "status": "success"]
    }
}
