package com.packtracker.fragments

import android.Manifest
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.google.android.material.floatingactionbutton.FloatingActionButton
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.ChatData
import com.packtracker.models.ChatMessage
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.io.File

/**
 * WhatsApp-style support chat for regular users: green/white bubbles,
 * photo messages (gallery or camera), 5s polling, date separators.
 */
class ChatFragment : Fragment() {

    companion object {
        private const val AUTO_REFRESH_MS = 5000L
        private const val MAX_IMAGE_BYTES = 10L * 1024 * 1024
    }

    private lateinit var rvMessages: RecyclerView
    private lateinit var tvEmpty: TextView
    private lateinit var tvOnlineStatus: TextView
    private lateinit var etMessage: EditText
    private lateinit var btnSend: FloatingActionButton
    private lateinit var btnAttach: ImageButton
    private lateinit var layoutPreview: View
    private lateinit var ivPreview: ImageView
    private lateinit var tvPreviewName: TextView

    private var messages: List<ChatMessage> = emptyList()
    private var pendingImageUri: Uri? = null
    private var cameraImageFile: File? = null
    private val handler = Handler(Looper.getMainLooper())
    private var autoRefreshRunnable: Runnable? = null

    private val galleryLauncher =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (result.resultCode == Activity.RESULT_OK) {
                val uri = result.data?.data ?: return@registerForActivityResult
                showPreview(uri)
            }
        }

    private val cameraLauncher =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (result.resultCode == Activity.RESULT_OK) {
                cameraImageFile?.let { file ->
                    val uri = FileProvider.getUriForFile(
                        requireContext(), requireContext().packageName + ".fileprovider", file
                    )
                    showPreview(uri)
                }
            }
        }

    private val cameraPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            if (granted) launchCamera() else {
                try { Toast.makeText(context, "Camera permission needed to take photos", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
            }
        }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_chat, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        // Admins get the conversation list instead of the user support chat.
        if (com.packtracker.utils.Session.isAdmin(requireContext())) {
            try {
                findNavController().navigate(
                    R.id.adminChatFragment,
                    null,
                    androidx.navigation.navOptions {
                        popUpTo(R.id.chatFragment) { inclusive = true }
                    }
                )
            } catch (_: Exception) {}
            return
        }

        rvMessages = view.findViewById(R.id.rvMessages)
        tvEmpty = view.findViewById(R.id.tvEmpty)
        tvOnlineStatus = view.findViewById(R.id.tvOnlineStatus)
        etMessage = view.findViewById(R.id.etMessage)
        btnSend = view.findViewById(R.id.btnSend)
        btnAttach = view.findViewById(R.id.btnAttach)
        layoutPreview = view.findViewById(R.id.layoutPreview)
        ivPreview = view.findViewById(R.id.ivPreview)
        tvPreviewName = view.findViewById(R.id.tvPreviewName)

        rvMessages.layoutManager = LinearLayoutManager(requireContext()).apply { stackFromEnd = true }

        btnSend.setOnClickListener {
            val text = etMessage.text.toString().trim()
            val image = pendingImageUri
            if (text.isEmpty() && image == null) return@setOnClickListener
            if (image != null) {
                sendMessageWithImage(if (text.isEmpty()) null else text, image)
            } else {
                sendMessageText(text)
            }
        }

        btnAttach.setOnClickListener { showAttachSheet() }

        view.findViewById<ImageButton>(R.id.btnRemovePreview)?.setOnClickListener {
            pendingImageUri = null
            layoutPreview.visibility = View.GONE
        }

        loadMessages()
        startAutoRefresh()
    }

    override fun onResume() {
        super.onResume()
        startAutoRefresh()
    }

    override fun onPause() {
        super.onPause()
        stopAutoRefresh()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        stopAutoRefresh()
    }

    // ── Sending ────────────────────────────────────────────────────────────

    private fun sendMessageText(text: String) {
        if (!isAdded) return
        etMessage.text?.clear()
        ApiClient.getService(requireContext()).sendChat(message = text)
            .enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (isAdded) loadMessages()
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    if (isAdded) try { Toast.makeText(context, "Failed to send", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            })
    }

    private fun sendMessageWithImage(text: String?, imageUri: Uri) {
        if (!isAdded) return
        val ctx = context ?: return
        try { Toast.makeText(ctx, "Sending photo…", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}

        try {
            val inputStream = ctx.contentResolver.openInputStream(imageUri)
            val bytes = inputStream?.use { it.readBytes() }
            if (bytes == null || bytes.size > MAX_IMAGE_BYTES) {
                try { Toast.makeText(ctx, "Image too large (max 10 MB)", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                return
            }
            val tempFile = File.createTempFile("chat_", ".jpg", ctx.cacheDir)
            tempFile.outputStream().use { it.write(bytes) }

            val filePart = MultipartBody.Part.createFormData(
                "attachment", tempFile.name, tempFile.asRequestBody("image/jpeg".toMediaTypeOrNull())
            )
            val actionPart = "ybt_mobile_send_chat".toRequestBody("text/plain".toMediaTypeOrNull())
            val messagePart = (text ?: "").toRequestBody("text/plain".toMediaTypeOrNull())

            etMessage.text?.clear()
            clearPreview()

            ApiClient.getService(ctx).sendChatMultipart(action = actionPart, message = messagePart, file = filePart)
                .enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                        if (isAdded) loadMessages()
                    }
                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        if (isAdded) try { Toast.makeText(context, "Failed to send photo", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    }
                })
        } catch (e: Exception) {
            try { Toast.makeText(ctx, "Could not read image", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
        }
    }

    // ── Attach flow ────────────────────────────────────────────────────────

    private fun showAttachSheet() {
        val options = arrayOf("📷 Choose from gallery", "📸 Take a photo")
        AlertDialog.Builder(requireContext())
            .setTitle("Send photo")
            .setItems(options) { _, which ->
                when (which) {
                    0 -> launchGallery()
                    1 -> checkCameraAndLaunch()
                }
            }
            .show()
    }

    private fun launchGallery() {
        if (!isAdded) return
        val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
            type = "image/*"
            addCategory(Intent.CATEGORY_OPENABLE)
        }
        galleryLauncher.launch(Intent.createChooser(intent, "Select a photo"))
    }

    private fun checkCameraAndLaunch() {
        if (!isAdded) return
        if (ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.CAMERA)
            == PackageManager.PERMISSION_GRANTED) {
            launchCamera()
        } else {
            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    private fun launchCamera() {
        if (!isAdded) return
        val ctx = context ?: return
        try {
            val file = File.createTempFile("camera_", ".jpg", ctx.cacheDir)
            cameraImageFile = file
            val uri = FileProvider.getUriForFile(ctx, ctx.packageName + ".fileprovider", file)
            val intent = Intent(android.provider.MediaStore.ACTION_IMAGE_CAPTURE).apply {
                putExtra(android.provider.MediaStore.EXTRA_OUTPUT, uri)
            }
            cameraLauncher.launch(intent)
        } catch (e: Exception) {
            try { Toast.makeText(ctx, "Camera unavailable", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
        }
    }

    private fun showPreview(uri: Uri) {
        if (!isAdded) return
        pendingImageUri = uri
        layoutPreview.visibility = View.VISIBLE
        Glide.with(this).load(uri).centerCrop().into(ivPreview)
        tvPreviewName.text = "Photo selected — add a message or tap send"
    }

    private fun clearPreview() {
        pendingImageUri = null
        layoutPreview.visibility = View.GONE
    }

    // ── Loading / polling ─────────────────────────────────────────────────

    private fun startAutoRefresh() {
        stopAutoRefresh()
        autoRefreshRunnable = object : Runnable {
            override fun run() {
                if (isAdded) {
                    loadMessagesSilent()
                    handler.postDelayed(this, AUTO_REFRESH_MS)
                }
            }
        }
        handler.postDelayed(autoRefreshRunnable!!, AUTO_REFRESH_MS)
    }

    private fun stopAutoRefresh() {
        autoRefreshRunnable?.let { handler.removeCallbacks(it) }
        autoRefreshRunnable = null
    }

    private fun loadMessages() {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getChat()
            .enqueue(object : Callback<ApiResponse<ChatData>> {
                override fun onResponse(call: Call<ApiResponse<ChatData>>, response: Response<ApiResponse<ChatData>>) {
                    if (!isAdded) return
                    messages = response.body()?.data?.messages ?: emptyList()
                    renderMessages(animateToBottom = true)
                    tvOnlineStatus.text = "online"
                }
                override fun onFailure(call: Call<ApiResponse<ChatData>>, t: Throwable) {
                    if (!isAdded) return
                    tvOnlineStatus.text = "connecting…"
                }
            })
    }

    private fun loadMessagesSilent() {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getChat()
            .enqueue(object : Callback<ApiResponse<ChatData>> {
                override fun onResponse(call: Call<ApiResponse<ChatData>>, response: Response<ApiResponse<ChatData>>) {
                    if (!isAdded) return
                    val fresh = response.body()?.data?.messages ?: emptyList()
                    if (fresh != messages) {
                        val wasAtBottom = isScrolledToBottom()
                        val newCount = fresh.size != messages.size
                        messages = fresh
                        renderMessages(scrollToBottom = (wasAtBottom && newCount) || messages.isEmpty())
                    }
                }
                override fun onFailure(call: Call<ApiResponse<ChatData>>, t: Throwable) {}
            })
    }

    private fun lastId(list: List<ChatMessage>): Long = list.lastOrNull()?.id ?: 0L

    private fun renderMessages(animateToBottom: Boolean = false, scrollToBottom: Boolean = false) {
        if (!isAdded) return
        rvMessages.adapter = WhatsAppChatAdapter(messages, showSenderNames = false) { m ->
            if (!m.attachment.isNullOrBlank()) openImageViewer(m.attachment)
        }
        tvEmpty.visibility = if (messages.isEmpty()) View.VISIBLE else View.GONE
        rvMessages.visibility = if (messages.isEmpty()) View.GONE else View.VISIBLE
        if (messages.isNotEmpty() && (animateToBottom || scrollToBottom)) {
            rvMessages.scrollToPosition(messages.size - 1)
        }
    }

    private fun isScrolledToBottom(): Boolean {
        val lm = rvMessages.layoutManager as? LinearLayoutManager ?: return true
        return lm.findLastVisibleItemPosition() >= (rvMessages.adapter?.itemCount ?: 0) - 2
    }

    private fun openImageViewer(rawUrl: String) {
        try {
            // Remap localhost for the emulator, as elsewhere in the app.
            val url = rawUrl
                .replace("http://localhost/", "http://10.0.2.2/")
                .replace("https://localhost/", "https://10.0.2.2/")
                .replace("http://127.0.0.1/", "http://10.0.2.2/")
            val dialog = com.google.android.material.bottomsheet.BottomSheetDialog(requireContext())
            val iv = ImageView(requireContext())
            iv.adjustViewBounds = true
            Glide.with(this).load(url).into(iv)
            dialog.setContentView(iv)
            dialog.show()
        } catch (_: Exception) {}
    }
}
