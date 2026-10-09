<?php

namespace App\Services\Catalog;

use App\Models\ProductImage;
use App\Services\ImageProcessor;
use Illuminate\Support\Facades\Log;
use Throwable;

class CatalogImage
{
    public function localPath(?ProductImage $image): ?string
    {
        if (! $image) {
            return null;
        }
        try {
            if ($image->external_url || ! ImageProcessor::isProcessed($image->path)) {
                throw new \RuntimeException('Imagen pendiente de convertir con images:convert.');
            }
            $root = realpath(public_path('storage'));
            $path = realpath(public_path('storage/'.$image->path.'.jpg'));
            if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_readable($path)) {
                throw new \RuntimeException('JPEG local ausente o sin permiso de lectura.');
            }
            $info = @getimagesize($path);
            if (! $info || $info[2] !== IMAGETYPE_JPEG) {
                throw new \RuntimeException('El archivo PDF no es un JPEG válido.');
            }

            return $path;
        } catch (Throwable $exception) {
            Log::warning('El catálogo mostrará un placeholder de imagen.', ['product_id' => $image->product_id, 'image_id' => $image->id, 'cause' => $exception->getMessage()]);

            return null;
        }
    }
}
