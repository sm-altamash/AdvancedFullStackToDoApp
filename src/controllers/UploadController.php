<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/controllers/UploadController.php

class UploadController {

    public function handleUpload() {
        // 1. Check for the file. TinyMCE sends it as 'file'
        if (empty($_FILES['file'])) {
            $this->sendError('No file uploaded.');
            return;
        }

        $file = $_FILES['file'];

        // 2. Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('Upload failed with error code: ' . $file['error']);
            return;
        }

        // 3. Security: Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes)) {
            $this->sendError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
            return;
        }

        // 4. Create a unique, safe filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_filename = uniqid('task-img-') . '.' . $file_extension;
        
        // 5. Define upload path
        $upload_dir = __DIR__ . '/../public/uploads/';
        $upload_path = $upload_dir . $safe_filename;

        // 6. Move the file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // 7. Success! Return the JSON that TinyMCE expects
            // The location must be the public-facing URL
            $public_url = '/uploads/' . $safe_filename;
            
            http_response_code(200);
            echo json_encode(['location' => $public_url]);
        } else {
            $this->sendError('Failed to move uploaded file.');
        }
    }

    private function sendError($message) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => $message]);
    }
}
?>