<?php
/**
 * Secure file upload helper.
 */

declare(strict_types=1);

if (!defined('GIMS_APP')) { exit('Direct access denied'); }

/**
 * Handle an uploaded image. Returns the relative path (relative to /assets/uploads)
 * or null when no file was uploaded. Throws RuntimeException on failure.
 */
function handleImageUpload(array $file, string $subDir = 'products', int $maxBytes = 2097152): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed with error code ' . $file['error']);
    }

    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File is larger than ' . round($maxBytes / 1048576, 1) . ' MB.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload source.');
    }

    // MIME validation via finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/pjpeg'=> 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, GIF or WEBP images are allowed.');
    }

    // Additional safety: verify it's a real image
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $ext      = $allowed[$mime];
    $filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $baseDir  = UPLOADS_PATH . '/' . trim($subDir, '/');
    if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $target = $baseDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    @chmod($target, 0644);

    return trim($subDir, '/') . '/' . $filename;
}

function deleteUploadedFile(?string $relativePath): void
{
    if (!$relativePath) return;
    $path = UPLOADS_PATH . '/' . ltrim($relativePath, '/');
    if (is_file($path)) { @unlink($path); }
}