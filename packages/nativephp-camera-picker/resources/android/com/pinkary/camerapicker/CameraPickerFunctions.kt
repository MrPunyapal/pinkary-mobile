package com.pinkary.camerapicker

import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction

/**
 * Namespace: "Camera.*"
 *
 * The bridge entry point behind Native\Mobile\Camera::pickImages(). A picker
 * is asynchronous by nature, so this returns as soon as the system picker has
 * been handed the request; the chosen files arrive through
 * Native\Mobile\Events\Gallery\MediaSelected, not through this return value.
 */
object CameraPickerFunctions {

    class PickMedia(private val activity: FragmentActivity) : BridgeFunction {

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val mediaType = parameters["mediaType"] as? String ?: "all"
            val multiple = parameters["multiple"] as? Boolean ?: false
            val maxItems = (parameters["maxItems"] as? Number)?.toInt() ?: 1
            val id = parameters["id"] as? String
            val event = parameters["event"] as? String

            Log.d(
                CameraPicker.TAG,
                "Pick requested - type: $mediaType, multiple: $multiple, max: $maxItems"
            )

            // The bridge call does not arrive on the main thread, and both the
            // fragment transaction and the launcher need it there.
            Handler(Looper.getMainLooper()).post {
                try {
                    CameraPicker.currentContext = activity.applicationContext

                    CameraPicker.configure(mediaType, multiple, maxItems, id, event)
                    CameraPicker.fragment(activity).pick()
                } catch (e: Exception) {
                    // Already past the return value, so the failure has to reach
                    // PHP as the event rather than as this call's response —
                    // otherwise the composer waits forever on a pick that
                    // never happens, showing nothing at all.
                    Log.e(CameraPicker.TAG, "Could not open the picker: ${e.message}", e)

                    CameraPicker.fail(event, id, e.message ?: "Could not open the photo picker")
                }
            }

            // status is what PendingMediaPicker::start() checks for. The picker
            // has been handed over, not finished — the result is a later event.
            return mapOf("success" to true, "status" to "success")
        }
    }
}
