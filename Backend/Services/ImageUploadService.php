<?php
// Backend/Services/ImageUploadService.php

/**
 * ImageUploadService – validates and moves uploaded image files.
 *
 * Two upload destinations are supported out of the box:
 *   - menu images  → Frontend/uploads/menu/
 *   - staff photos → Frontend/ADMIN/uploads/staff/
 *
 * Usage:
 *   $uploader = new ImageUploadService('menu');
 *   $path = $uploader->handle($_FILES['image']);  // returns relative path or null
 */
class ImageUploadService
{
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_EXTS  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_SIZE      = 2 * 1024 * 1024; // 2 MB

    // Where files land on disk – keyed by a logical name
    private const DIRS = [
        'menu'  => 'Frontend/uploads/menu/',
        'staff' => 'Frontend/ADMIN/uploads/staff/',
    ];

    private string $type;

    /** @param string $type  One of 'menu' or 'staff'. */
    public function __construct(string $type = 'menu')
    {
        if (!array_key_exists($type, self::DIRS)) {
            throw new InvalidArgumentException("Unknown upload type: $type");
        }
        $this->type = $type;
    }

    /**
     * Process an uploaded file from the $_FILES superglobal entry.
     *
     * @param array|null $file  $_FILES['fieldname'] element, or null if absent.
     * @return string|null  Relative path stored in the DB, or null if no file.
     * @throws RuntimeException  On validation or move failure.
     */
    public function handle(?array $file): ?string
    {
        // No file chosen — not an error
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error code: ' . $file['error']);
        }

        // Validate size
        if ($file['size'] > self::MAX_SIZE) {
            throw new RuntimeException('Image must be 2 MB or smaller.');
        }

        // Validate MIME via finfo (server-side, not extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_TYPES)) {
            throw new RuntimeException('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
        }

        // Validate extension as a secondary guard
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS)) {
            throw new RuntimeException('Invalid file extension.');
        }

        // Build destination path
        $relDir  = self::DIRS[$this->type];
        $absDir  = $this->projectRoot() . $relDir;

        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $prefix   = ($this->type === 'staff') ? 'staff_' : 'menu_';
        $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $absDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        return $relDir . $filename;
    }

    /**
     * Delete an image file given its relative path (as stored in the DB).
     * Silently ignores missing files.
     */
    public function delete(string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }
        $full = $this->projectRoot() . $relativePath;
        if (file_exists($full)) {
            unlink($full);
        }
    }

    /** Resolve the absolute project root (one level above Backend/). */
    private function projectRoot(): string
    {
        return rtrim(dirname(__DIR__, 1), '/\\') . '/../';
    }
}
