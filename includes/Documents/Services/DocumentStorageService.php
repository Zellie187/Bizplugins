<?php

declare(strict_types=1);

namespace BizHub\Documents\Services;

use RuntimeException;

/**
 * Handles physical storage of document files on disk.
 *
 * This is the only class permitted to read or write document files
 * directly; all other code should go through DocumentService.
 *
 * @package BizHub\Documents\Services
 */
final class DocumentStorageService
{
    /**
     * Move an uploaded file into permanent storage for an owner.
     *
     * @return array{path:string,size:int,mime_type:string}
     */
    public function store(
        string $temporaryPath,
        string $ownerType,
        string $ownerUuid,
        string $originalFilename
    ): array {
        if (! is_file($temporaryPath)) {
            throw new RuntimeException(
                sprintf('Uploaded file "%s" does not exist.', $temporaryPath)
            );
        }

        $directory = $this->directoryFor($ownerType, $ownerUuid);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Unable to create document storage directory "%s".', $directory)
            );
        }

        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $storedName = uniqid('doc_', true) . ($extension !== '' ? '.' . $extension : '');
        $destination = $directory . $storedName;

        if (! copy($temporaryPath, $destination)) {
            throw new RuntimeException('Unable to store the uploaded document.');
        }

        return [
            'path' => $destination,
            'size' => (int) filesize($destination),
            'mime_type' => $this->detectMimeType($destination),
        ];
    }

    /**
     * Detect a file's MIME type, tolerating environments without the
     * fileinfo extension enabled.
     */
    private function detectMimeType(string $path): string
    {
        if (\function_exists('mime_content_type')) {
            $type = mime_content_type($path);

            if ($type !== false) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Permanently delete a stored file.
     */
    public function delete(string $filePath): void
    {
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Determine whether a stored file still exists on disk.
     */
    public function exists(string $filePath): bool
    {
        return is_file($filePath);
    }

    /**
     * Resolve the storage directory for an owner's documents.
     */
    private function directoryFor(string $ownerType, string $ownerUuid): string
    {
        return sprintf(
            '%sdocuments/%s/%s/',
            \defined('BIZHUB_STORAGE_PATH') ? BIZHUB_STORAGE_PATH : sys_get_temp_dir() . '/bizhub/',
            $ownerType,
            $ownerUuid
        );
    }
}
