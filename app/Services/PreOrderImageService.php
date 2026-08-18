<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class PreOrderImageService
{
    private const RELATIVE_DIRECTORY = 'uploads/preorders';

    public function store(UploadedFile $image): string
    {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $image->getMimeType();
        if (! isset($allowed[$mime])) {
            throw new RuntimeException('Unsupported vehicle image type.');
        }

        $directory = public_path(self::RELATIVE_DIRECTORY);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('The vehicle image upload directory could not be created.');
        }

        $filename = Str::uuid()->toString().'.'.$allowed[$mime];
        $image->move($directory, $filename);

        return self::RELATIVE_DIRECTORY.'/'.$filename;
    }

    public function delete(?string $relativePath): void
    {
        if (! $relativePath || ! str_starts_with($relativePath, self::RELATIVE_DIRECTORY.'/')) {
            return;
        }

        $base = realpath(public_path(self::RELATIVE_DIRECTORY));
        $path = realpath(public_path($relativePath));
        if ($base && $path && str_starts_with($path, $base.DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }
}
