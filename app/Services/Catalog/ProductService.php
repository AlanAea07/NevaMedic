<?php

namespace App\Services\Catalog;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductService
{
    public function __construct(private ImageProcessor $processor) {}

    public function save(Product $product, array $data, User $actor): Product
    {
        $data['is_published'] = (bool) $data['is_available'];
        $newPaths = [];
        $oldPaths = [];
        try {
            DB::transaction(function () use ($product, $data, $actor, &$newPaths, &$oldPaths) {
                if ($product->exists) {
                    $product->setRawAttributes(Product::whereKey($product->id)->lockForUpdate()->firstOrFail()->getAttributes(), true);
                }
                $action = $product->exists ? 'updated' : 'created';
                $before = $product->exists ? $this->snapshot($product) : null;
                $product->fill(Arr::except($data, ['sectors', 'image_source', 'image_file', 'image_url', 'variants', 'gallery_files', 'gallery_urls', 'remove_images']))->save();
                $product->sectors()->sync($data['sectors']);
                if (in_array($data['image_source'] ?? 'keep', ['file', 'url'], true)) {
                    $image = $product->images()->whereNull('product_variant_id')->where('is_primary', true)->first();
                    $this->saveImage($product, $image, $data, null, true, $newPaths, $oldPaths);
                }
                foreach ($product->images()->whereNull('product_variant_id')->where('is_primary', false)->whereIn('id', $data['remove_images'] ?? [])->get() as $image) {
                    $oldPaths[] = $image->path;
                    $image->delete();
                }
                foreach ($data['gallery_files'] ?? [] as $file) {
                    $this->saveImage($product, null, ['image_source' => 'file', 'image_file' => $file], null, false, $newPaths, $oldPaths);
                }
                foreach (array_filter($data['gallery_urls'] ?? []) as $url) {
                    $this->saveImage($product, null, ['image_source' => 'url', 'image_url' => $url], null, false, $newPaths, $oldPaths);
                }
                if ($product->has_variants && array_key_exists('variants', $data)) {
                    $retained = [];
                    foreach ($data['variants'] as $row) {
                        $variant = empty($row['id']) ? $product->variants()->make() : $product->variants()->findOrFail($row['id']);
                        $variant->fill(['color' => $row['color'], 'color_hex' => $row['color_hex'], 'size' => '', 'design' => null, 'is_available' => true])->save();
                        $retained[] = $variant->id;
                        if ($row['image_source'] !== 'keep') {
                            $this->saveImage($product, $product->images()->where('product_variant_id', $variant->id)->first(), $row, $variant->id, false, $newPaths, $oldPaths);
                        }
                        foreach ($product->images()->where('product_variant_id', $variant->id)->whereIn('id', $row['remove_images'] ?? [])->get() as $image) {
                            $oldPaths[] = $image->path;
                            $image->delete();
                        }
                        foreach ($row['gallery_files'] ?? [] as $file) {
                            $this->saveImage($product, null, ['image_source' => 'file', 'image_file' => $file], $variant->id, false, $newPaths, $oldPaths);
                        }
                        foreach (array_filter($row['gallery_urls'] ?? []) as $url) {
                            $this->saveImage($product, null, ['image_source' => 'url', 'image_url' => $url], $variant->id, false, $newPaths, $oldPaths);
                        }
                    }
                    foreach ($product->variants()->whereNotIn('id', $retained)->get() as $variant) {
                        foreach ($variant->images as $image) {
                            $oldPaths[] = $image->path;
                            $image->delete();
                        }
                        $variant->delete();
                    }
                }
                ActivityLog::record($actor, $action, 'product', $product->id, $before, $this->snapshot($product), ['uploads' => $this->uploadMetadata($data)]);
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $path) {
                $this->processor->deleteFiles($path);
            }
            throw $exception;
        }
        foreach (array_unique($oldPaths) as $path) {
            $this->processor->deleteIfUnused($path);
        }

        return $product;
    }

    private function saveImage(Product $product, ?ProductImage $image, array $data, ?int $variantId, bool $primary, array &$newPaths, array &$oldPaths): void
    {
        try {
            $path = $this->processor->process($data['image_source'] === 'file' ? $data['image_file'] : $data['image_url'], $product->id);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['image_'.$data['image_source'] => 'No se pudo procesar una fotografía: '.$exception->getMessage()]);
        }
        $newPaths[] = $path;
        if ($image) {
            $oldPaths[] = $image->path;
        }
        $attributes = ['path' => $path, 'external_url' => null, 'alt' => $product->name, 'is_primary' => $primary, 'product_variant_id' => $variantId, 'sort_order' => $image?->sort_order ?? ($primary ? 0 : (int) $product->images()->max('sort_order') + 1)];
        if ($image) {
            $image->update($attributes);
        } else {
            $product->images()->create($attributes);
        }
    }

    public function delete(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($product);
            $product->delete();
            ActivityLog::record($actor, 'deleted', 'product', $product->id, $before, $this->snapshot($product));
        });
    }

    public function restore(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $product = Product::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($product->purge_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['product' => 'El plazo de recuperación de 14 días ha terminado.']);
            }
            $before = $this->snapshot($product);
            $product->restore();
            ActivityLog::record($actor, 'restored', 'product', $product->id, $before, $this->snapshot($product));
        });
    }

    public function purgeExpired(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $product = Product::onlyTrashed()->whereKey($id)->lockForUpdate()->first();
            if (! $product || $product->purge_at->isFuture()) {
                return false;
            }
            $before = $this->snapshot($product);
            foreach ($product->images->pluck('path')->filter()->unique() as $path) {
                DB::table('pending_image_deletions')->insertOrIgnore(['path' => $path, 'created_at' => now()]);
            }
            ActivityLog::record(null, 'purged', 'product', $product->id, $before, null, ['reason' => 'retention_expired', 'retention_days' => Product::TRASH_RETENTION_DAYS]);
            $product->forceDelete();

            return true;
        });
    }

    public function snapshot(Product $product): array
    {
        $product->load(['images', 'variants', 'sectors']);

        return array_merge($product->attributesToArray(), [
            'purge_at' => $product->purge_at?->toIso8601String(),
            'images' => $product->images->map(fn ($image) => $image->only(['id', 'path', 'external_url', 'alt', 'is_primary', 'product_variant_id', 'sort_order']))->all(),
            'variants' => $product->variants->map(fn ($variant) => $variant->only(['id', 'color', 'color_hex', 'size', 'design', 'is_available']))->all(),
            'sectors' => $product->sectors->map(fn ($sector) => $sector->only(['id', 'name']))->all(),
        ]);
    }

    private function uploadMetadata(array $data): array
    {
        $uploads = [];
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $uploads[] = ['field' => $key, 'name' => $value->getClientOriginalName(), 'bytes' => $value->getSize(), 'mime' => $value->getMimeType()];
            } elseif (is_array($value)) {
                $uploads = array_merge($uploads, $this->uploadMetadata($value));
            }
        }

        return $uploads;
    }
}
