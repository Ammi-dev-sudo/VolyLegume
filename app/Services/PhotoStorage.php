<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stockage unifié des photographies agricoles.
 *
 * Le disque est configurable (`MEDIA_DISK`) afin de conserver durablement les
 * images en production sur un stockage objet compatible S3, sans dépendre du
 * disque temporaire de Render. Les images sont redimensionnées et compressées
 * lorsque l'extension GD est disponible.
 */
class PhotoStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('filesystems.media_disk', 'public');
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $optimized = $this->optimize($file);

        if ($optimized === null) {
            return $file->storePublicly($directory, $this->diskName());
        }

        $path = trim($directory, '/').'/'.$optimized['name'];

        $this->disk()->put($path, $optimized['contents'], [
            'visibility' => 'public',
            'ContentType' => $optimized['mime'],
        ]);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : $this->disk()->url($path);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $this->disk()->exists($path);
    }

    /**
     * @return array{name: string, contents: string, mime: string}|null
     */
    private function optimize(UploadedFile $file): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $raw = @file_get_contents($file->getRealPath());

        if ($raw === false) {
            return null;
        }

        $source = @imagecreatefromstring($raw);

        if ($source === false) {
            return null;
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            if ($width < 1 || $height < 1) {
                return null;
            }

            $maxDimension = 1600;
            $ratio = min(1, $maxDimension / max($width, $height));
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($canvas === false) {
                return null;
            }

            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($canvas, null, 82);
            $contents = ob_get_clean();
            imagedestroy($canvas);

            if ($contents === false || $contents === '') {
                return null;
            }

            return [
                'name' => Str::uuid()->toString().'.jpg',
                'contents' => $contents,
                'mime' => 'image/jpeg',
            ];
        } catch (Throwable) {
            return null;
        } finally {
            imagedestroy($source);
        }
    }
}
