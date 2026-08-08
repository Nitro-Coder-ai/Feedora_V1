<?php
/**
 * Story File Handler - Handles file upload, validation, and deletion
 * Follows Single Responsibility Principle
 */
require_once __DIR__ . '/CloudinaryService.php';

class StoryFileHandler {
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct($uploadDir = 'uploads/stories/', $maxFileSize = 52428800) { // 50MB default
        $this->uploadDir = $uploadDir;
        $this->maxFileSize = $maxFileSize;
        $this->allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/quicktime', 'video/webm', 'video/mov'
        ];
        
        $this->ensureUploadDirectory();
    }
    
    /**
     * Ensure upload directory exists
     */
    private function ensureUploadDirectory() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Validate uploaded file
     */
    public function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => $this->getUploadErrorMessage($file['error'])
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'error' => 'File size too large. Maximum ' . ($this->maxFileSize / 1024 / 1024) . 'MB allowed.'
            ];
        }
        
        // Check file type
        if (!in_array($file['type'], $this->allowedTypes)) {
            return [
                'valid' => false,
                'error' => 'Invalid file type. Only images and videos are allowed.'
            ];
        }
        
        // Additional security check: sniff the real MIME from file contents.
        // Guarded with function_exists so a server without the `fileinfo`
        // extension degrades gracefully (relying on the client MIME + size checks
        // above) instead of throwing a fatal "undefined function" that silently
        // kills the whole upload request.
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            // Accept the explicit allow-list OR any image/* or video/* family. Video
            // containers (mov/mp4/webm) are routinely sniffed as variants that are NOT
            // in the static list (e.g. video/x-m4v, application/mp4); rejecting those
            // is why legitimate video stories never reached Cloudinary or the database.
            $isAllowedFamily = is_string($mimeType)
                && (strncmp($mimeType, 'image/', 6) === 0 || strncmp($mimeType, 'video/', 6) === 0);

            if ($mimeType !== false && !in_array($mimeType, $this->allowedTypes, true) && !$isAllowedFamily) {
                return [
                    'valid' => false,
                    'error' => 'File type mismatch detected.'
                ];
            }
        }
        
        return [
            'valid' => true,
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Upload file to server with unique naming (directly to Cloudinary only)
     */
    public function uploadFile($file, $userId) {
        try {
            // Generate unique filename with timestamp and random component
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $timestamp = time();
            $microtime = microtime(true);
            $random = mt_rand(1000, 9999);
            $newFileName = "story_{$userId}_{$timestamp}_{$microtime}_{$random}.{$fileExtension}";

            // Upload to Cloudinary; the secure URL keeps the original
            // extension so downstream image/video detection still works.
            $cloud = CloudinaryService::upload($file['tmp_name'], 'feedora/stories', "story_{$userId}_{$timestamp}_{$random}");
            if ($cloud['success']) {
                // Resolve the media type from Cloudinary's authoritative
                // resource_type, falling back to the URL extension if absent.
                $resourceType = $cloud['resource_type'] ?? '';
                $mediaType = ($resourceType === 'video' || $resourceType === 'image')
                    ? $resourceType
                    : $this->getFileType($cloud['url']);

                // Compress BOTH images and videos with Cloudinary on-the-fly
                // delivery transforms (verified to return HTTP 200 for video):
                //   f_auto        → best format per browser (webp/avif images;
                //                   mp4 for Safari, webm/vp9 for Chrome on video)
                //   q_auto        → automatic quality-based compression (~70% off)
                //   c_limit,w_…   → cap only very large media; c_limit NEVER upscales
                //                   and preserves the original aspect ratio, so
                //                   nothing is cropped/zoomed in or out.
                $deliveryUrl = CloudinaryService::transform($cloud['url'], 1080);

                return [
                    'success' => true,
                    'file_path' => $deliveryUrl,
                    'filename' => $newFileName,
                    'public_id' => $cloud['public_id'],
                    'media_type' => $mediaType
                ];
            }

            return [
                'success' => false,
                'error' => 'Cloudinary upload failed: ' . ($cloud['error'] ?? 'Unknown error')
            ];
            
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'File upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete file from server
     */
    public function deleteFile($filePath) {
        try {
            if (empty($filePath)) {
                return true;
            }
            if (strpos($filePath, 'http') === 0) {
                CloudinaryService::destroyByUrl($filePath);
                return true;
            } elseif (file_exists($filePath)) {
                return unlink($filePath);
            }
            return true; // File doesn't exist, consider it deleted
        } catch (Exception $e) {
            error_log("File deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get file type (image or video)
     */
    public function getFileType($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'webm', 'ogg', 'avi'];
        
        return in_array($extension, $videoExtensions) ? 'video' : 'image';
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Get file size in human readable format
     */
    public function getFileSize($filePath) {
        if (!file_exists($filePath)) {
            return '0 B';
        }
        
        $size = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
}
