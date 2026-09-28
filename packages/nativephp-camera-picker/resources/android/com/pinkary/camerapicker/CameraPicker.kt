package com.pinkary.camerapicker

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import android.util.Log
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import org.json.JSONArray
import org.json.JSONObject
import java.io.File
import java.util.UUID

/**
 * The photo picker, and the state shared between the bridge function that
 * opens it and the fragment that receives the result.
 *
 * NativePHP ships `Native\Mobile\Camera::pickImages()` and
 * `PendingMediaPicker`, both of which call the `Camera.PickMedia` bridge
 * function — but no core implementation of that function exists, so
 * `start()` could only ever return false. This supplies it.
 *
 * The launchers live on a fragment rather than on the bridge function because
 * `registerForActivityResult` has to be called while the lifecycle owner is
 * still INITIALIZED, and a bridge function is constructed long after the
 * activity resumed. A fragment field initialiser runs at construction, which
 * is the window the API requires.
 */
object CameraPicker {

    const val TAG = "CameraPicker"

    const val FRAGMENT_TAG = "pinkary-camera-picker"

    /**
     * PickMultipleVisualMedia rejects a maximum below 2 and fixes its ceiling
     * at construction, so this is a property of the contract. A caller's own
     * `maxItems` is applied to the result instead.
     */
    private const val MAX_PICKER_ITEMS = 10

    private const val MEDIA_CACHE_DIR = "native-media"

    private const val DEFAULT_MIME = "image/jpeg"

    private const val DEFAULT_EVENT = "Native\\Mobile\\Events\\Gallery\\MediaSelected"

    @Volatile
    private var pendingEvent: String = DEFAULT_EVENT

    private var pendingId: String? = null
    private var pendingMediaType: String = "all"
    private var pendingMultiple: Boolean = false
    private var pendingMaxItems: Int = 1

    /**
     * Remember what the next pick should honour. Called on the main thread,
     * immediately before the launcher fires.
     */
    fun configure(mediaType: String, multiple: Boolean, maxItems: Int, id: String?, event: String?) {
        pendingMediaType = mediaType.ifBlank { "all" }
        pendingMultiple = multiple
        pendingMaxItems = if (maxItems > 0) maxItems else 1
        pendingId = id
        pendingEvent = event?.takeIf { it.isNotBlank() } ?: DEFAULT_EVENT
    }

    fun mediaType(): String = pendingMediaType

    fun multiple(): Boolean = pendingMultiple

    fun maxItems(): Int = pendingMaxItems

    fun request(): PickVisualMediaRequest = when (pendingMediaType.lowercase()) {
        "image" -> PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)
        "video" -> PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly)
        else -> PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageAndVideo)
    }

    /**
     * Copy what the user chose out of the content provider and hand PHP a
     * `files` array of readable paths.
     *
     * A content:// URI is not a path PHP can open, and the read grant is
     * revoked as soon as the pick finishes, so the bytes are copied first. The
     * cache is the right home for them: they stage an upload and the system is
     * free to reclaim them.
     */
    fun onPickedMedia(uris: List<Uri>) {
        val event = pendingEvent
        val id = pendingId
        val maxItems = pendingMaxItems
        pendingId = null

        val limited = uris.take(maxItems)
        val files = JSONArray()
        var copied = 0

        val context = currentContext

        if (context != null && limited.isNotEmpty()) {
            val directory = File(context.cacheDir, MEDIA_CACHE_DIR).apply { mkdirs() }

            for (uri in limited) {
                val entry = copyToCache(context, directory, uri) ?: continue
                files.put(entry)
                copied++
            }
        }

        val payload = JSONObject().apply {
            put("success", copied > 0)
            put("files", files)
            put("count", copied)
            // MediaSelected takes a distinct `cancelled` flag: an empty result
            // is a cancel, not a failure, and the composer should say nothing
            // at all in that case.
            put("cancelled", limited.isEmpty())
            if (id != null) {
                put("id", id)
            }
        }

        dispatch(event, payload.toString())
    }

    private fun copyToCache(context: Context, directory: File, uri: Uri): JSONObject? {
        return try {
            val resolver = context.contentResolver

            val displayName = resolver
                .query(uri, null, null, null, null)
                ?.use { cursor ->
                    val index = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                    if (index >= 0 && cursor.moveToFirst()) cursor.getString(index) else null
                }

            val mime = resolver.getType(uri) ?: DEFAULT_MIME
            val target = File(directory, "${UUID.randomUUID()}.${extensionFor(mime)}")

            val stream = resolver.openInputStream(uri) ?: return null

            stream.use { input ->
                target.outputStream().use { output -> input.copyTo(output) }
            }

            JSONObject().apply {
                put("path", target.absolutePath)
                put("name", displayName ?: target.name)
                put("mime", mime)
                put("size", target.length())
            }
        } catch (e: Exception) {
            Log.e(TAG, "Could not copy $uri into the cache: ${e.message}", e)
            null
        }
    }

    /**
     * Content providers under-report mime types more often than not, so an
     * unrecognised one lands as a jpg — which the server's image rules accept
     * — rather than under an extension nothing will decode.
     */
    private fun extensionFor(mime: String): String = when (mime.lowercase()) {
        "image/png" -> "png"
        "image/gif" -> "gif"
        "image/webp" -> "webp"
        else -> "jpg"
    }

    private fun dispatch(event: String, payloadJson: String) {
        Log.d(TAG, "Dispatching $event")
        NativeElementBridge.sendNativeEvent(event, payloadJson)
    }

    /**
     * Report a picker that could not be opened at all.
     *
     * PHP is already waiting on an event by the time the main-thread work
     * runs, so swallowing this leaves the composer spinning on a pick that
     * will never complete. MediaSelected carries an `error`, so use it.
     */
    fun fail(event: String?, id: String?, message: String) {
        val payload = JSONObject().apply {
            put("success", false)
            put("files", JSONArray())
            put("count", 0)
            put("cancelled", false)
            put("error", message)
            if (id != null) {
                put("id", id)
            }
        }

        dispatch(event?.takeIf { it.isNotBlank() } ?: DEFAULT_EVENT, payload.toString())
    }

    /**
     * Set by the bridge function before it launches, so the fragment can read
     * the cache without holding a reference to the activity.
     */
    @Volatile
    var currentContext: Context? = null

    /**
     * The headless fragment that owns the two launchers.
     */
    class MediaPickerFragment : Fragment() {

        // Field initialisers: this is the window registerForActivityResult
        // accepts, because the fragment is still INITIALIZED here.
        private val pickOne =
            registerForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
                CameraPicker.onPickedMedia(listOfNotNull(uri))
            }

        private val pickMany =
            registerForActivityResult(
                ActivityResultContracts.PickMultipleVisualMedia(MAX_PICKER_ITEMS)
            ) { uris -> CameraPicker.onPickedMedia(uris) }

        fun pick() {
            if (CameraPicker.multiple()) {
                pickMany.launch(CameraPicker.request())
            } else {
                pickOne.launch(CameraPicker.request())
            }
        }
    }

    /**
     * Find the fragment, adding it the first time. The bridge function is
     * constructed once per process but the picker can be opened many times, so
     * the fragment is reused rather than re-added.
     */
    fun fragment(activity: FragmentActivity): MediaPickerFragment {
        val existing = activity.supportFragmentManager.findFragmentByTag(FRAGMENT_TAG)

        if (existing is MediaPickerFragment) {
            return existing
        }

        val fragment = MediaPickerFragment()

        // commitNowAllowingStateLoss: the bridge call arrives on a background
        // thread at an arbitrary point in the activity's life, and the picker
        // has to be launchable from any of them.
        activity.supportFragmentManager
            .beginTransaction()
            .add(fragment, FRAGMENT_TAG)
            .commitNowAllowingStateLoss()

        return fragment
    }
}
