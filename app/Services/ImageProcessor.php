<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

class ImageProcessor
{
    public function __construct(private RemoteImageDownloader $downloader) {}

    public function process(UploadedFile|File|string $source, ?int $productId = null): string
    {
        $base = 'products/'.Str::uuid();
        try {
            if ($source instanceof File) {
                if ($source->getSize() > RemoteImageDownloader::MAX_BYTES) {
                    throw new RuntimeException('La imagen supera 10 MB.');
                }
                $bytes = file_get_contents($source->getPathname());
                if ($bytes === false) {
                    throw new RuntimeException('No se pudo leer la imagen.');
                }
            } else {
                $bytes = $this->downloader->download($source);
            }
            if (strlen($bytes) > RemoteImageDownloader::MAX_BYTES) {
                throw new RuntimeException('La imagen supera 10 MB.');
            }
            $info = @getimagesizefromstring($bytes);
            if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WebP válidas.');
            }
            // Bound decoded memory as well as compressed bytes.
            if ($info[0] * $info[1] > 20000000) {
                throw new RuntimeException('La imagen supera 20 megapíxeles. Reduce sus dimensiones.');
            }
            $image = Image::read($bytes)->scaleDown(width: 1000);
            $disk = Storage::disk('public');
            if (! $disk->put($base.'.webp', (string) $image->toWebp(quality: 80))
                || ! $disk->put($base.'.jpg', (string) $image->toJpeg(quality: 80))) {
                throw new RuntimeException('No se pudieron guardar ambas versiones de la imagen.');
            }

            return $base;
        } catch (Throwable $exception) {
            $this->deleteFiles($base);
            Log::warning('No se pudo procesar la imagen del producto.', ['product_id' => $productId, 'cause' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public static function isProcessed(?string $path): bool
    {
        return (bool) preg_match('~^products/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~i', $path ?? '');
    }

    public function complete(string $base): bool
    {
        return self::isProcessed($base) && Storage::disk('public')->exists($base.'.webp') && Storage::disk('public')->exists($base.'.jpg');
    }

    public function deleteIfUnused(?string $path): void
    {
        if ($path && ! ProductImage::where('path', $path)->exists()) {
            $this->deleteFiles($path);
        }
    }

    public function deleteFiles(string $path): void
    {
        if (! preg_match('~^(products|demo)/[a-zA-Z0-9._/-]+$~', $path) || str_contains($path, '..')) {
            return;
        }
        $paths = self::isProcessed($path) ? [$path.'.webp', $path.'.jpg'] : [$path];
        try {
            if (! Storage::disk('public')->delete($paths)) {
                throw new RuntimeException('El disco no pudo eliminar los archivos.');
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudieron limpiar archivos de imagen.', ['path' => $path, 'cause' => $exception->getMessage()]);
        }
    }
}
