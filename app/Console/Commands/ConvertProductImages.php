<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

class ConvertProductImages extends Command
{
    protected $signature = 'images:convert {--product= : ID de un producto} {--limit=0 : Máximo de imágenes a intentar; 0 significa todas}';

    protected $description = 'Convierte imágenes existentes a WebP/JPEG; omite pares completos y permite reanudar.';

    public function handle(ImageProcessor $processor): int
    {
        $converted = $skipped = $failed = 0;
        $limit = max(0, (int) $this->option('limit'));
        foreach (ProductImage::query()->when($this->option('product'), fn ($q, $id) => $q->where('product_id', $id))->lazyById(50) as $candidate) {
            if ($limit && $converted + $failed >= $limit) {
                break;
            }
            $newPath = $oldPath = null;
            try {
                // Same row lock as product editing, so conversion cannot overwrite a newer image.
                $result = DB::transaction(function () use ($candidate, $processor, &$newPath, &$oldPath) {
                    Product::withTrashed()->whereKey($candidate->product_id)->lockForUpdate()->firstOrFail();
                    $image = ProductImage::whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $image || (! $image->external_url && $processor->complete($image->path))) {
                        return false;
                    }
                    $oldPath = $image->path;
                    if ($image->external_url) {
                        $source = $image->external_url;
                    } else {
                        $path = $image->path;
                        if (ImageProcessor::isProcessed($path)) {
                            $path .= Storage::disk('public')->exists($path.'.webp') ? '.webp' : '.jpg';
                        }
                        $root = realpath(Storage::disk('public')->path(''));
                        $absolute = realpath(Storage::disk('public')->path($path));
                        if (! $root || ! $absolute || ! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR)) {
                            throw new \RuntimeException('Archivo local ausente o fuera del disco público.');
                        }
                        $source = new File($absolute);
                    }
                    $newPath = $processor->process($source, $image->product_id);
                    $image->update(['path' => $newPath, 'external_url' => null]);

                    return true;
                });
                if ($result) {
                    $processor->deleteIfUnused($oldPath);
                    $converted++;
                    $this->line('Convertida imagen '.$candidate->id.' (producto '.$candidate->product_id.').');
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                if ($newPath) {
                    $processor->deleteIfUnused($newPath);
                }
                $failed++;
                Log::warning('Falló la conversión de una imagen existente.', ['product_id' => $candidate->product_id, 'image_id' => $candidate->id, 'cause' => $exception->getMessage()]);
                $this->warn('Imagen '.$candidate->id.': '.$exception->getMessage());
            }
        }
        $this->info("Convertidas: $converted; omitidas: $skipped; fallidas: $failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
