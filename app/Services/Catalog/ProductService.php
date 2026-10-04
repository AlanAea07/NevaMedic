<?php

namespace App\Services\Catalog;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductService
{
    public function save(Product $product, array $data, User $actor): Product
    {
        $data['is_published'] = (bool) $data['is_available'];
        $newPaths = [];
        $oldPaths = [];
        try {
            DB::transaction(function () use ($product, $data, $actor, &$newPaths, &$oldPaths) {
                if ($product->exists) Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $action = $product->exists ? 'updated' : 'created';
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
                    }
                    foreach ($product->variants()->whereNotIn('id', $retained)->get() as $variant) {
                        foreach ($variant->images as $image) {
                            $oldPaths[] = $image->path;
                            $image->delete();
                        }
                        $variant->delete();
                    }
                }
                ActivityLog::create(['user_id' => $actor->id, 'action' => $action, 'entity_type' => 'product', 'entity_id' => $product->id]);
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $path) Storage::disk('public')->delete($path);
            throw $exception;
        }
        foreach (array_unique($oldPaths) as $path) {
            if ($path && str_starts_with($path, 'products/') && ! ProductImage::where('path', $path)->exists()) Storage::disk('public')->delete($path);
        }
        return $product;
    }

    private function saveImage(Product $product, ?ProductImage $image, array $data, ?int $variantId, bool $primary, array &$newPaths, array &$oldPaths): void
    {
        $path = '';
        if ($data['image_source'] === 'file') {
            $path = $data['image_file']->store('products', 'public');
            if (! $path) throw ValidationException::withMessages(['image_file' => 'No se pudo guardar la imagen. Intenta nuevamente.']);
            $newPaths[] = $path;
        }
        if ($image) $oldPaths[] = $image->path;
        $attributes = ['path' => $path, 'external_url' => $path ? null : $data['image_url'], 'alt' => $product->name, 'is_primary' => $primary, 'product_variant_id' => $variantId, 'sort_order' => $primary ? 0 : 1];
        if ($image) $image->update($attributes);
        else $product->images()->create($attributes);
    }

    public function delete(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            $product->delete();
            ActivityLog::create(['user_id' => $actor->id, 'action' => 'deleted', 'entity_type' => 'product', 'entity_id' => $product->id]);
        });
    }
}
