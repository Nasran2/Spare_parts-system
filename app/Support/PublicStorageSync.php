<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class PublicStorageSync
{
    public static function syncFile(string $relativePath): void
    {
        $relativePath = ltrim($relativePath, '/');
        $source = Storage::disk('public')->path($relativePath);

        if (!is_file($source)) {
            return;
        }

        $destination = public_path('storage/' . $relativePath);
        $destinationDir = dirname($destination);

        if (!is_dir($destinationDir)) {
            @mkdir($destinationDir, 0755, true);
        }

        @copy($source, $destination);
    }

    public static function removeFile(string $relativePath): void
    {
        $relativePath = ltrim($relativePath, '/');
        $destination = public_path('storage/' . $relativePath);

        if (is_file($destination)) {
            @unlink($destination);
        }
    }

    public static function linkAndSyncAll(): array
    {
        $linkCreated = false;
        $filesSynced = 0;

        try {
            Artisan::call('storage:link');
            $linkCreated = is_link(public_path('storage')) || is_dir(public_path('storage'));
        } catch (\Throwable $e) {
            $linkCreated = false;
        }

        $sourceRoot = storage_path('app/public');
        $destinationRoot = public_path('storage');

        if (!is_dir($destinationRoot)) {
            @mkdir($destinationRoot, 0755, true);
        }

        if (is_dir($sourceRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $sourcePath = $file->getPathname();
                $relativePath = ltrim(str_replace($sourceRoot, '', $sourcePath), DIRECTORY_SEPARATOR);
                $destinationPath = $destinationRoot . DIRECTORY_SEPARATOR . $relativePath;
                $destinationDir = dirname($destinationPath);

                if (!is_dir($destinationDir)) {
                    @mkdir($destinationDir, 0755, true);
                }

                if (@copy($sourcePath, $destinationPath)) {
                    $filesSynced++;
                }
            }
        }

        return [
            'link_created' => $linkCreated,
            'files_synced' => $filesSynced,
        ];
    }
}
