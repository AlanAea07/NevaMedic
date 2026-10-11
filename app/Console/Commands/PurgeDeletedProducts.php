<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductService;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PurgeDeletedProducts extends Command
{
    protected $signature = 'products:purge-trash';

    protected $description = 'Elimina productos con 14 días en papelera y reintenta la limpieza de sus imágenes.';

    public function handle(ProductService $service): int
    {
        $count = 0;
        $failed = false;
        Product::onlyTrashed()->where('deleted_at', '<=', now()->subDays(Product::TRASH_RETENTION_DAYS))
            ->select('id')->chunkById(100, function ($products) use ($service, &$count, &$failed) {
                foreach ($products as $product) {
                    try {
                        $count += (int) $service->purgeExpired($product->id);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error("No se pudo eliminar el producto #{$product->id}.");
                        $failed = true;
                    }
                }
            });
        DB::table('pending_image_deletions')->orderBy('id')->chunkById(100, function ($jobs) use (&$failed) {
            foreach ($jobs as $job) {
                try {
                    if (! ProductImage::where('path', $job->path)->exists()) {
                        if (! preg_match('~^(products|demo)/[a-zA-Z0-9._/-]+$~', $job->path) || str_contains($job->path, '..')) {
                            throw new RuntimeException('Ruta de imagen no válida para limpieza.');
                        }
                        $paths = ImageProcessor::isProcessed($job->path) ? [$job->path.'.webp', $job->path.'.jpg'] : [$job->path];
                        if (! Storage::disk('public')->delete($paths)) {
                            throw new RuntimeException('No se pudieron eliminar los archivos de imagen.');
                        }
                    }
                    DB::table('pending_image_deletions')->where('id', $job->id)->delete();
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Limpieza pendiente #{$job->id}; se reintentará en la siguiente ejecución.");
                    $failed = true;
                }
            }
        });
        $this->info("Productos eliminados definitivamente: {$count}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
