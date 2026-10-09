# Código completo: procesamiento de imágenes

Archivos nuevos o modificados, con su ruta relativa a C:/xampp/htdocs/NevaMedic.
No se requiere migración de esquema. composer.lock se incluye completo en el ZIP.
Las instrucciones están en docs/image-processing.md.

## app/Services/ImageProcessor.php

````php
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

````

## app/Services/RemoteImageDownloader.php

````php
<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class RemoteImageDownloader
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function download(string $url): string
    {
        for ($hop = 0; $hop < 5; $hop++) {
            [$host, $port, $ip] = $this->validateDestination($url);
            $sink = tmpfile();
            if ($sink === false) {
                throw new RuntimeException('No se pudo crear el archivo temporal de descarga.');
            }
            try {
                $response = Http::timeout(10)->connectTimeout(5)
                    ->withUserAgent('Mozilla/5.0 (compatible; NevaMedic/1.0; ProductImages)')
                    ->withOptions([
                        'allow_redirects' => false,
                        'sink' => $sink,
                        'curl' => [
                            CURLOPT_PROXY => '',
                            CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)],
                        ],
                        'on_headers' => function (ResponseInterface $response) {
                            if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                                throw new RuntimeException('La imagen supera 10 MB.');
                            }
                        },
                        'progress' => function ($total, $downloaded) {
                            if (max($total, $downloaded) > self::MAX_BYTES) {
                                throw new RuntimeException('La imagen supera 10 MB.');
                            }
                        },
                    ])->get($url);

                if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    $location = $response->header('Location');
                    if (! $location) {
                        throw new RuntimeException('Redirección sin destino.');
                    }
                    // Every redirect gets fresh DNS validation and a pinned connection.
                    $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                    continue;
                }
                if (! $response->successful()) {
                    throw new RuntimeException('El servidor de imágenes respondió HTTP '.$response->status().'.');
                }
                if (! str_starts_with(strtolower($response->header('Content-Type') ?? ''), 'image/')) {
                    throw new RuntimeException('El enlace no devuelve una imagen; puede ser una página de vista previa o requerir permiso.');
                }
                $stream = $response->toPsrResponse()->getBody();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                $bytes = '';
                while (! $stream->eof()) {
                    $bytes .= $stream->read(65536);
                    if (strlen($bytes) > self::MAX_BYTES) {
                        throw new RuntimeException('La imagen supera 10 MB.');
                    }
                }

                return $bytes;
            } finally {
                if (is_resource($sink)) {
                    fclose($sink);
                }
            }
        }
        throw new RuntimeException('El enlace tiene demasiadas redirecciones.');
    }

    private function validateDestination(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || ! in_array($port, [80, 443], true)
            || ! $host || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('Usa una URL pública http/https sin credenciales y con puerto 80 o 443.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveAddresses($host);
        if (! $ips) {
            throw new RuntimeException('No se pudo resolver el servidor de la imagen.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                || str_starts_with(strtolower($ip), '::ffff:')) {
                throw new RuntimeException('No se permiten direcciones internas, privadas o reservadas.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    protected function resolveAddresses(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}

````

## app/Services/Catalog/ProductService.php

````php
<?php

namespace App\Services\Catalog;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ImageProcessor;
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
                    Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                }
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
        $attributes = ['path' => $path, 'external_url' => null, 'alt' => $product->name, 'is_primary' => $primary, 'product_variant_id' => $variantId, 'sort_order' => $primary ? 0 : 1];
        if ($image) {
            $image->update($attributes);
        } else {
            $product->images()->create($attributes);
        }
    }

    public function delete(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            $product->delete();
            ActivityLog::create(['user_id' => $actor->id, 'action' => 'deleted', 'entity_type' => 'product', 'entity_id' => $product->id]);
        });
    }
}

````

## app/Services/Catalog/CatalogImage.php

````php
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

````

## app/Services/Catalog/CatalogPdf.php

````php
<?php

namespace App\Services\Catalog;

use App\Models\Sector;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;

class CatalogPdf
{
    public function __construct(private CatalogImage $images) {}

    public function data(Sector $sector): array
    {
        abort_unless($sector->is_active, 404);
        $products = $sector->products()->published()->with(['images', 'variants'])->orderBy('name')->get();
        abort_if($products->isEmpty(), 404, 'Este sector todavía no tiene productos disponibles.');
        $theme = config('catalogs.themes.'.$sector->slug, config('catalogs.default_theme'));
        $downloadUrl = route('catalogs.download', $sector->slug);
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 12), new SvgImageBackEnd));
        $qr = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($downloadUrl));
        $photos = $products->mapWithKeys(fn ($product) => [$product->id => $this->images->localPath($product->cover_image)])->all();
        $whatsapp = preg_match('/^[1-9][0-9]{9,14}$/', (string) config('neva.whatsapp')) ? config('neva.whatsapp') : null;

        return compact('sector', 'products', 'theme', 'downloadUrl', 'qr', 'photos', 'whatsapp') + ['generatedAt' => now(), 'siteUrl' => url('/')];
    }

    public function render(Sector $sector): string
    {
        $data = $this->data($sector);
        $pdf = Pdf::loadView('pdf.sector-catalog', $data)->setPaper('a4')->setOptions([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'chroot' => [public_path(), storage_path('app/public')],
            'defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true, 'dpi' => 144,
        ]);
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(500, 812, '{PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.38, 0.44, 0.51]);

        return $pdf->output();
    }
}

````

## app/Console/Commands/ConvertProductImages.php

````php
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

````

## app/Models/ProductImage.php

````php
<?php

namespace App\Models;

use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_variant_id', 'path', 'external_url', 'alt', 'sort_order', 'is_primary'];

    public function getUrlAttribute(): string
    {
        $path = ImageProcessor::isProcessed($this->path) ? $this->path.'.webp' : $this->path;

        return $this->external_url ?: asset('storage/'.ltrim($path, '/'));
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

````

## app/Http/Requests/Admin/SaveProductRequest.php

````php
<?php

namespace App\Http\Requests\Admin;

use App\Support\ExternalImageUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->boolean('sizes_present')) {
            $this->merge(['available_sizes' => $this->input('available_sizes', [])]);
        }
        $this->merge(['image_url' => ExternalImageUrl::normalize($this->input('image_url'))]);
        $variants = $this->input('variants');
        if (is_array($variants)) {
            foreach ($variants as &$variant) {
                if (is_array($variant)) {
                    $variant['image_url'] = ExternalImageUrl::normalize($variant['image_url'] ?? null);
                    if (is_string($variant['color'] ?? null)) {
                        $variant['color'] = trim($variant['color']);
                    }
                }
            }
            unset($variant);
            $this->merge(['variants' => $variants]);
        }
        if (is_array($this->input('gallery_urls'))) {
            $this->merge(['gallery_urls' => array_map([ExternalImageUrl::class, 'normalize'], $this->input('gallery_urls'))]);
        }
    }

    public function messages(): array
    {
        return [
            'available_sizes.*.in' => 'Selecciona una talla de la lista disponible.',
            'available_sizes.*.distinct' => 'No repitas una talla.',
            'variants.required_if' => 'Agrega al menos un color con su fotografía.',
            'variants.*.color.required' => 'Escribe el nombre del color.',
            'variants.*.color.distinct' => 'No repitas el mismo color en este producto.',
            'variants.*.image_file.required_if' => 'Selecciona la fotografía JPG, PNG o WebP de este color.',
            'variants.*.image_file.mimetypes' => 'La fotografía del color debe ser JPG, PNG o WebP.',
            'variants.*.image_url.required_if' => 'Pega el enlace de la fotografía de este color.',
            'variants.*.image_url.url' => 'El enlace de cada color debe comenzar con http:// o https://.',
            'gallery_files.*.mimetypes' => 'Las fotografías de la galería deben ser JPG, PNG o WebP.',
            'gallery_files.*.max' => 'Cada fotografía debe pesar como máximo 10 MB.',
            'slug.required' => 'Escribe el nombre para el enlace, por ejemplo: filipina-azul.',
            'slug.regex' => 'Usa solo minúsculas, números y guiones. Ejemplo: filipina-azul. No escribas https:// ni .com.',
            'slug.unique' => 'Ese nombre para el enlace ya existe. Prueba otro, como filipina-azul-clasica.',
            'image_file.required_if' => 'Selecciona una imagen JPG, PNG o WebP.',
            'image_file.image' => 'El archivo debe ser una imagen JPG, PNG o WebP válida.',
            'image_file.mimetypes' => 'Solo se permiten imágenes JPG, PNG o WebP. Cambiar la extensión no convierte el formato del archivo.',
            'image_file.extensions' => 'Usa una extensión .jpg, .jpeg, .png o .webp.',
            'image_file.max' => 'La imagen no debe superar 10 MB.',
            'image_url.required_if' => 'Pega el enlace público de la imagen.',
            'image_url.url' => 'Usa un enlace de imagen que empiece con http:// o https://.',
            'image_url.max' => 'El enlace de imagen es demasiado largo.',
        ];
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageProducts();
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? 0;

        return [
            'available_sizes' => ['sometimes', 'array', 'max:'.count(config('neva.sizes'))],
            'available_sizes.*' => ['required', 'string', 'distinct', Rule::in(config('neva.sizes'))],
            'has_variants' => ['sometimes', 'boolean'],
            'variants' => ['exclude_unless:has_variants,1', 'required_if:has_variants,1', 'array', 'min:1', 'max:20'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $productId)],
            'variants.*.color' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'variants.*.color_hex' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'variants.*.image_source' => ['required', Rule::in(['keep', 'file', 'url'])],
            'variants.*.image_file' => ['exclude_unless:variants.*.image_source,file', 'required_if:variants.*.image_source,file', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'],
            'variants.*.image_url' => ['exclude_unless:variants.*.image_source,url', 'required_if:variants.*.image_source,url', 'string', 'url:http,https', 'max:2048'],
            'gallery_files' => ['nullable', 'array', 'max:8'],
            'gallery_files.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'],
            'gallery_urls' => ['nullable', 'array', 'max:8'],
            'gallery_urls.*' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $productId)->where('is_primary', 0)->whereNull('product_variant_id')],
            'image_source' => ['nullable', Rule::in(['keep', 'file', 'url'])],
            'image_file' => ['exclude_unless:image_source,file', 'required_if:image_source,file', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'],
            'image_url' => ['exclude_unless:image_source,url', 'required_if:image_source,url', 'string', 'url:http,https', 'max:2048'],
            'name' => ['required', 'string', 'max:150'],
            'product_type' => ['nullable', Rule::in(array_keys(config('neva.product_types')))],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products')->ignore($this->route('product'))],
            'short_description' => ['required', 'string', 'max:280'],
            'description' => ['nullable', 'string', 'max:10000'],
            'material' => ['nullable', 'string', 'max:150'],
            'care_instructions' => ['nullable', 'string', 'max:2000'],
            'reference_price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'is_available' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sectors' => ['required', 'array', 'min:1'],
            'sectors.*' => ['required', 'integer', 'distinct', Rule::exists('sectors', 'id')],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('has_variants')) {
                return;
            }
            foreach ($this->input('variants', []) as $index => $variant) {
                if ($variant['image_source'] === 'keep' && ! $this->route('product')?->images()->where('product_variant_id', $variant['id'] ?? 0)->exists()) {
                    $validator->errors()->add("variants.$index.image_source", 'Cada color necesita su fotografía: sube un JPG, PNG o WebP o pega un enlace.');
                }
            }
        }];
    }
}

````

## config/image.php

````php
<?php

use Intervention\Image\Drivers\Gd\Driver;

return [
    'driver' => Driver::class,
    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => false,
        'blendingColor' => 'ffffff',
        'strip' => true,
    ],
];

````

## database/seeders/DemoProductSeeder.php

````php
<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sector;
use App\Services\ImageProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\File\File;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SectorSeeder::class);
        $items = [
            ['filipina-esencial', 'Filipina esencial', 'filipinas', 'clinico', 490, [['blanco', 'Blanco', '#ecebe6'], ['cafe', 'Café', '#795548']]],
            ['filipina-balance', 'Filipina Balance', 'filipinas', 'clinico', 550, [['marino', 'Azul marino', '#193758'], ['verde', 'Verde', '#438379']]],
            ['bata-clinica', 'Bata clínica', 'batas-clinicas', 'clinico', 690, [['blanco', 'Blanco', '#f0efeb'], ['azul', 'Azul', '#6197be']]],
            ['pantalon-clinico', 'Pantalón clínico', 'pantalones', 'clinico', 420, [['marino', 'Azul marino', '#193758'], ['negro', 'Negro', '#30343b']]],
            ['gorro-clinico', 'Gorro clínico', 'gorros-cofias', 'clinico', 150, [['azul', 'Azul', '#457ea4'], ['verde', 'Verde', '#438379']]],
            ['conjunto-quirurgico', 'Conjunto quirúrgico', 'conjuntos-quirurgicos', 'medico', 950, [['marino', 'Azul marino', '#193758'], ['cafe', 'Café', '#795548']]],
            ['bata-medica', 'Bata médica', 'batas-medicas', 'medico', 750, [['blanco', 'Blanco', '#f0efeb'], ['gris', 'Gris', '#9ca6b0']]],
            ['camisa-industrial', 'Camisa industrial', 'camisas-industriales', 'industrial', 620, [['azul', 'Azul', '#35678c'], ['gris', 'Gris', '#818e9c']]],
            ['overol-industrial', 'Overol industrial', 'overoles', 'industrial', 1150, [['marino', 'Azul marino', '#193758'], ['gris', 'Gris', '#818e9c']]],
            ['chaleco-seguridad', 'Chaleco de seguridad', 'chalecos', 'industrial', 380, [['naranja', 'Naranja', '#e99239'], ['amarillo', 'Amarillo', '#d5c642']]],
        ];
        foreach ($items as [$slug, $name, $type, $sector, $price, $colors]) {
            if (Product::withTrashed()->where('slug', 'demo-'.$slug)->exists()) {
                continue;
            }
            $newPaths = [];
            try {
                DB::transaction(function () use ($slug, $name, $type, $sector, $price, $colors, &$newPaths) {
                    $product = Product::create(['slug' => 'demo-'.$slug, 'name' => $name.' · Demo', 'product_type' => $type, 'short_description' => 'Producto de demostración con dos colores. Imagen y precio de ejemplo.', 'description' => 'Muestra para probar el catálogo y la selección de colores; no representa existencias ni especificaciones finales del cliente.', 'reference_price' => $price, 'currency' => 'MXN', 'is_available' => true, 'is_published' => true, 'is_featured' => in_array($slug, ['filipina-esencial', 'conjunto-quirurgico', 'camisa-industrial']), 'has_variants' => true]);
                    $product->sectors()->sync([Sector::where('slug', $sector)->firstOrFail()->id]);
                    foreach ($colors as $index => [$key, $label, $hex]) {
                        $filename = $slug.'-'.$key.'.webp';
                        $source = database_path('demo-images/'.$filename);
                        if (! is_file($source)) {
                            throw new \RuntimeException('Falta la ilustración '.$filename);
                        }
                        $path = app(ImageProcessor::class)->process(new File($source), $product->id);
                        $newPaths[] = $path;
                        if ($index === 0) {
                            $product->images()->create(['path' => $path, 'alt' => $name.' · Muestra', 'is_primary' => true]);
                        }
                        $variant = $product->variants()->create(['color' => $label, 'color_hex' => $hex, 'size' => '', 'is_available' => true]);
                        $product->images()->create(['path' => $path, 'alt' => $name.' '.$label.' · Muestra', 'product_variant_id' => $variant->id]);
                    }
                });
            } catch (\Throwable $exception) {
                foreach ($newPaths as $path) {
                    app(ImageProcessor::class)->deleteIfUnused($path);
                }
                throw $exception;
            }
        }
    }
}

````

## resources/js/product-editor.js

````javascript
export function externalImageUrl(value) {
    try {
        const url = new URL(value);
        if (!['http:', 'https:'].includes(url.protocol)) return '';
        if (url.hostname === 'drive.google.com') {
            const id = url.pathname.match(/\/file\/d\/([a-zA-Z0-9_-]+)/)?.[1] || url.searchParams.get('id');
            if (id && /^[a-zA-Z0-9_-]+$/.test(id)) {
                return `https://drive.google.com/thumbnail?id=${id}&sz=w1200`;
            }
        }
        return url.href;
    } catch {
        return '';
    }
}

export default (initial) => ({
    source: initial.source,
    url: initial.url || '',
    existing: initial.existing || '',
    fileUrl: '',
    preview: '',
    error: '',
    failed: false,
    name: '',
    description: '',
    price: '',
    available: false,
    sectors: '',
    hasVariants: !!initial.hasVariants,
    variants: (initial.variants || []).map((row, index) => ({ ...row, _key: index + 1, fileUrl: '', error: '' })),
    nextKey: (initial.variants || []).length + 1,
    previewColor: '',
    galleryLinks: initial.galleryLinks || [],
    galleryPreviews: [],
    galleryError: '',
    get cardImage() {
        const row = this.hasVariants && this.variants.find(row => row._key === this.previewColor);
        return row ? this.variantImage(row) : this.preview;
    },
    variantImage(row) {
        return row.image_source === 'file' ? row.fileUrl : row.image_source === 'url' ? externalImageUrl(row.image_url) : row.existing;
    },
    addVariant() {
        this.variants.push({ _key: this.nextKey++, id: '', color: '', color_hex: '#1765d8', image_source: 'file', image_url: '', existing: '', fileUrl: '', error: '' });
    },
    removeVariant(index) {
        const row = this.variants[index];
        if (row.fileUrl) URL.revokeObjectURL(row.fileUrl);
        if (this.previewColor === row._key) this.previewColor = '';
        this.variants.splice(index, 1);
    },
    fileError(file) {
        if (!/\.(jpe?g|png|webp)$/i.test(file.name) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return 'Solo se permiten archivos JPG, PNG o WebP.';
        return file.size > 10 * 1024 * 1024 ? 'La imagen no debe superar 10 MB.' : '';
    },
    selectVariantFile(row, event) {
        if (row.fileUrl) URL.revokeObjectURL(row.fileUrl);
        row.fileUrl = ''; row.error = ''; this.failed = false;
        const file = event.target.files[0];
        if (!file) return;
        row.error = this.fileError(file);
        if (row.error) event.target.value = '';
        else row.fileUrl = URL.createObjectURL(file);
    },
    selectGalleryFiles(event) {
        this.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        this.galleryPreviews = [];
        const files = [...event.target.files];
        this.galleryError = files.length > 8 ? 'Selecciona como máximo 8 imágenes.' : files.map(file => this.fileError(file)).find(Boolean) || '';
        if (this.galleryError) event.target.value = '';
        else this.galleryPreviews = files.map(file => URL.createObjectURL(file));
    },
    init() {
        this.$nextTick(() => this.sync());
        this.$watch('source', () => this.refreshImage());
        this.refreshImage();
    },
    sync() {
        const form = this.$el.closest('form');
        this.name = form.elements.namedItem('name').value;
        this.description = form.elements.namedItem('short_description').value;
        this.price = form.elements.namedItem('reference_price').value;
        this.available = form.querySelector('input[type=checkbox][name=is_available]').checked;
        this.sectors = [...form.querySelectorAll('input[name="sectors[]"]:checked')]
            .filter(input => input.dataset.active === '1')
            .map(input => input.dataset.label).join(' · ');
    },
    get formattedPrice() {
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(this.price) || 0) + ' MXN';
    },
    refreshImage() {
        this.failed = false;
        this.preview = this.source === 'file' ? this.fileUrl : this.source === 'url' ? externalImageUrl(this.url) : this.existing;
    },
    selectFile(event) {
        if (this.fileUrl) URL.revokeObjectURL(this.fileUrl);
        this.fileUrl = '';
        this.error = '';
        const file = event.target.files[0];
        if (file) {
            if (!/\.(jpe?g|png|webp)$/i.test(file.name) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.error = 'Solo se permiten archivos JPG, PNG o WebP.';
            } else if (file.size > 10 * 1024 * 1024) {
                this.error = 'La imagen no debe superar 10 MB.';
            } else {
                this.fileUrl = URL.createObjectURL(file);
            }
            if (this.error) event.target.value = '';
        }
        this.refreshImage();
    },
    destroy() {
        if (this.fileUrl) URL.revokeObjectURL(this.fileUrl);
        this.galleryPreviews.forEach(url => URL.revokeObjectURL(url));
        this.variants.forEach(row => { if (row.fileUrl) URL.revokeObjectURL(row.fileUrl); });
    },
});

````

## resources/views/admin/products/partials/form.blade.php

````php
@php
    $currentImage = $product->images->whereNull('product_variant_id')->first();
    $imageSource = old('image_source', $currentImage ? 'keep' : 'file');
    $imageState = ['source' => $imageSource, 'url' => old('image_url', $currentImage?->external_url), 'existing' => $currentImage?->url];
    $colorRows = old('variants', $product->variants->map(fn ($variant) => ['id' => $variant->id, 'color' => $variant->color, 'color_hex' => $variant->color_hex ?: '#1765d8', 'image_source' => 'keep'])->all());
    $imageState['variants'] = collect(is_array($colorRows) ? $colorRows : [])->filter(fn ($row) => is_array($row))->map(function ($row) use ($product) {
        $image = !empty($row['id']) ? $product->images->firstWhere('product_variant_id', $row['id']) : null;
        return ['id' => $row['id'] ?? '', 'color' => $row['color'] ?? '', 'color_hex' => $row['color_hex'] ?? '#1765d8', 'image_source' => $row['image_source'] ?? 'file', 'image_url' => $row['image_url'] ?? $image?->external_url ?? '', 'existing' => $image?->url ?? ''];
    })->values()->all();
    $imageState['hasVariants'] = (bool) old('has_variants', $product->has_variants);
    $imageState['galleryLinks'] = old('gallery_urls', []);
@endphp
<div class="grid gap-6 xl:grid-cols-3" x-data="productEditor({{ Illuminate\Support\Js::from($imageState) }})" @input="sync()" @change="sync()">
<div class="panel space-y-5 xl:col-span-2">
<h2 class="text-xl font-semibold">Información general</h2>
<x-ui.category-search name="product_type" :options="config('neva.product_types')" :value="old('product_type', $product->product_type)" />
@foreach(['name' => ['Nombre', 150], 'short_description' => ['Descripción corta', 280], 'material' => ['Material (opcional)', 150]] as $field => [$label, $length])
<label class="block">{{ $label }}<input class="form-input" name="{{ $field }}" value="{{ old($field, $product->$field) }}" maxlength="{{ $length }}" @required(in_array($field, ['name', 'slug', 'short_description']))></label>
@endforeach
<label class="block" for="product-slug">Nombre para el enlace del producto
    <input id="product-slug" class="form-input" name="slug" value="{{ old('slug', $product->slug) }}" maxlength="180" placeholder="filipina-azul" pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="product-slug-help" required>
</label>
<p id="product-slug-help" class="text-sm text-neva-muted">Ejemplo: <strong>filipina-azul</strong>. Escribe un nombre corto, sin espacios ni acentos y separado con guiones. No pongas https:// ni .com.</p>
@error('slug')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
<label class="block">Descripción<textarea class="form-input" name="description" rows="5" maxlength="10000">{{ old('description', $product->description) }}</textarea></label>
<label class="block">Cuidados (opcional)<textarea class="form-input" name="care_instructions" rows="3" maxlength="2000" placeholder="Puedes dejar este campo vacío.">{{ old('care_instructions', $product->care_instructions) }}</textarea></label>
@include('admin.products.partials.sizes')
<fieldset class="space-y-4 border-t border-neva-line pt-5">
    <legend class="text-xl font-semibold">Foto principal del producto</legend>
    <p class="text-sm text-neva-muted">Esta foto se mostrará en el listado. Elige cómo agregarla:</p>
    <div class="flex flex-wrap gap-3">
        <label class="flex cursor-pointer items-center gap-2 rounded border border-neva-line p-3" :class="{ 'border-neva-blue bg-neva-mist': source === 'file' }"><input type="radio" name="image_source" value="file" x-model="source" @checked($imageSource === 'file')>Subir desde mi equipo</label>
        <label class="flex cursor-pointer items-center gap-2 rounded border border-neva-line p-3" :class="{ 'border-neva-blue bg-neva-mist': source === 'url' }"><input type="radio" name="image_source" value="url" x-model="source" @checked($imageSource === 'url')>Pegar un enlace</label>
        <label class="flex cursor-pointer items-center gap-2 rounded border border-neva-line p-3"><input type="radio" name="image_source" value="keep" x-model="source" @checked($imageSource === 'keep')>{{ $currentImage ? 'Conservar foto actual' : 'Agregar después' }}</label>
    </div>
    <div x-cloak x-show="source === 'file'" class="space-y-2">
        <label class="block">Archivo JPG, PNG o WebP<input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-input" :disabled="source !== 'file'" :required="source === 'file'" @change="selectFile($event)"></label>
        <p class="text-sm text-neva-muted">JPG, PNG o WebP, máximo 10 MB. Si el formulario muestra errores, vuelve a seleccionar el archivo.</p>
    </div>
    <div x-cloak x-show="source === 'url'" class="space-y-2">
        <label class="block">Enlace público de la imagen<input type="url" name="image_url" class="form-input" maxlength="2048" placeholder="https://ejemplo.com/imagen.webp" x-model="url" @input.debounce.400ms="refreshImage()" :disabled="source !== 'url'" :required="source === 'url'"></label>
        <p class="text-sm text-neva-muted">Pega un enlace directo a la imagen o un enlace de Google Drive compartido con «Cualquier persona con el enlace». Un enlace de búsqueda de Google o de una página web no es una imagen.</p>
    </div>
    <p x-cloak x-show="error" x-text="error" class="text-sm text-red-700" role="alert"></p>
    @error('image_file')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
    @error('image_url')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</fieldset>
@include('admin.products.partials.gallery')
@include('admin.products.partials.variants')
</div>
<div class="panel space-y-6 self-start">
<label class="block">Precio de referencia (MXN)<input type="number" step="0.01" min="0" max="99999999.99" class="form-input" name="reference_price" value="{{ old('reference_price', $product->reference_price) }}" required></label>
<fieldset><legend class="font-semibold">Sectores</legend><div class="mt-3 space-y-3">
@forelse($sectors as $sector)
<label class="flex items-center gap-3"><input type="checkbox" name="sectors[]" value="{{ $sector->id }}" data-label="{{ $sector->name }}" data-active="{{ $sector->is_active ? '1' : '0' }}" @checked(in_array($sector->id, old('sectors', $product->sectors->modelKeys())))>{{ $sector->name }} {{ $sector->is_active ? '' : '(inactivo)' }}</label>
@empty
<p class="text-neva-muted">Pide al administrador que cree un sector.</p>
@if(auth()->user()->isAdministrator())
<a href="{{ route('admin.sectors.create') }}" class="text-neva-blue">Crear sector</a>
@endif
@endforelse
</div></fieldset>
@foreach(['is_available' => 'Disponible · mostrar en el catálogo', 'is_featured' => 'Destacado en inicio'] as $field => $label)
<input type="hidden" name="{{ $field }}" value="0"><label class="flex items-center gap-3"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $product->$field))>{{ $label }}</label>
@endforeach
<p class="text-sm text-neva-muted">Al desactivar «Disponible», el producto queda oculto para los visitantes.</p>
<button class="btn-primary w-full">Guardar producto</button><a href="{{ route('admin.products.index') }}" class="btn-secondary w-full">Cancelar</a>
<section class="border-t border-neva-line pt-6" aria-label="Previsualización de la tarjeta">
    <h2 class="mb-4 font-semibold">Así se verá en el catálogo</h2>
    <p x-cloak x-show="!available" class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">Producto oculto. Activa «Disponible» para mostrarlo en el catálogo.</p>
    <article class="overflow-hidden rounded border border-neva-line bg-white">
        <template x-if="cardImage"><img :src="cardImage" alt="Previsualización de la imagen principal" class="h-72 w-full object-cover" x-show="!failed" x-on:error="failed = true" @load="failed = false" referrerpolicy="no-referrer"></template>
        <div x-show="!cardImage || failed" class="flex h-72 items-center justify-center bg-neva-mist p-6 text-center text-neva-muted" x-text="failed ? 'No se pudo cargar la imagen. Revisa el enlace y sus permisos.' : 'Fotografía pendiente'"></div>
        <div x-cloak x-show="hasVariants" class="flex flex-wrap gap-2 p-3"><button type="button" class="rounded border px-2 py-1 text-sm" @click="previewColor = ''; failed = false">Principal</button><template x-for="row in variants" :key="row._key"><button type="button" class="rounded border px-2 py-1 text-sm" :class="{ 'border-neva-blue': previewColor === row._key }" @click="previewColor = row._key; failed = false" x-text="row.color || 'Nuevo color'"></button></template></div>
        <div class="space-y-3 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-neva-blue" x-text="sectors"></p>
            <h3 class="font-display text-3xl" x-text="name || 'Nombre del producto'"></h3>
            <p class="text-sm text-neva-muted" x-text="description || 'Descripción corta del producto'"></p>
            <p class="font-semibold" x-text="formattedPrice"></p>
            <p class="text-sm" x-text="(available ? 'Disponible' : 'No disponible') + ' · Precio de referencia'"></p>
            <span class="inline-block text-sm font-semibold text-neva-blue">Ver producto →</span>
        </div>
    </article>
</section>
</div>
</div>

````

## resources/views/admin/products/partials/gallery.blade.php

````php
<details class="space-y-4 border-t border-neva-line pt-5" @if($errors->has('gallery_files.*') || $errors->has('gallery_urls.*')) open @endif>
    <summary class="cursor-pointer text-xl font-semibold">Más fotografías del producto (opcional)</summary>
    <p class="text-sm text-neva-muted">Agrega diferentes vistas de la prenda. Las fotografías de cada color se agregan más abajo.</p>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @foreach($product->images->whereNull('product_variant_id')->where('is_primary', false) as $image)
        <label class="rounded border border-neva-line p-2"><img src="{{ $image->url }}" alt="{{ $image->alt }}" class="h-28 w-full object-cover" referrerpolicy="no-referrer"><span class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}" @checked(in_array($image->id, old('remove_images', [])))>Quitar al guardar</span></label>
        @endforeach
    </div>
    <label class="block">Añadir archivos JPG, PNG o WebP<input type="file" multiple name="gallery_files[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-input" @change="selectGalleryFiles($event)"></label>
    <p class="text-sm text-neva-muted">Hasta 8 archivos por carga, máximo 10 MB cada uno.</p>
    <p x-show="galleryError" x-text="galleryError" class="text-sm text-red-700" role="alert"></p>
    <div class="grid grid-cols-3 gap-2"><template x-for="url in galleryPreviews" :key="url"><img :src="url" class="h-28 w-full rounded object-cover" alt="Nueva fotografía de la galería"></template></div>
    <template x-for="(link, index) in galleryLinks" :key="index"><label class="block">Enlace de fotografía adicional<input type="url" :name="`gallery_urls[${index}]`" x-model="galleryLinks[index]" class="form-input" placeholder="https://ejemplo.com/prenda.webp"><span class="text-xs text-neva-muted">Deja vacío para omitir este enlace.</span></label></template>
    <button type="button" class="btn-secondary" @click="galleryLinks.push('')" :disabled="galleryLinks.length >= 8">Añadir enlace a la galería</button>
</details>

````

## resources/views/admin/products/partials/variants.blade.php

````php
<section class="space-y-4 border-t border-neva-line pt-5">
    <h2 class="text-xl font-semibold">Variantes por color</h2>
    <input type="hidden" name="has_variants" value="0">
    <label class="flex items-center gap-3"><input type="checkbox" name="has_variants" value="1" x-model="hasVariants">Este producto tiene variantes de color</label>
    <p class="text-sm text-neva-muted">Cada color debe tener su propia fotografía. Al desactivar esta opción, los colores se conservan pero no se muestran al visitante.</p>
    <fieldset :disabled="!hasVariants" x-show="hasVariants" x-cloak class="space-y-5">
        <template x-for="(row, index) in variants" :key="row._key">
            <div class="space-y-3 rounded border border-neva-line p-4">
                <div class="flex items-center justify-between"><h3 class="font-semibold" x-text="row.color || 'Nuevo color'"></h3><button type="button" class="text-sm text-red-700" @click="removeVariant(index)">Quitar color</button></div>
                <input type="hidden" :name="`variants[${index}][id]`" :value="row.id">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label>Nombre del color<input class="form-input" :name="`variants[${index}][color]`" x-model="row.color" placeholder="Blanco, café, azul marino…" maxlength="80" :required="hasVariants"></label>
                    <label>Muestra de color<input type="color" class="form-input h-11" :name="`variants[${index}][color_hex]`" x-model="row.color_hex"></label>
                </div>
                <label class="block">Fotografía de este color<select class="form-input" :name="`variants[${index}][image_source]`" x-model="row.image_source" @change="failed = false"><option value="file">Subir WebP</option><option value="url">Usar enlace externo</option><option value="keep" :disabled="!row.existing">Conservar fotografía actual</option></select></label>
                <label class="block" x-show="row.image_source === 'file'">Archivo JPG, PNG o WebP<input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :name="`variants[${index}][image_file]`" class="form-input" :disabled="row.image_source !== 'file'" :required="hasVariants && row.image_source === 'file'" @change="selectVariantFile(row, $event)"></label>
                <label class="block" x-show="row.image_source === 'url'">Enlace público<input type="url" class="form-input" :name="`variants[${index}][image_url]`" x-model="row.image_url" maxlength="2048" :disabled="row.image_source !== 'url'" :required="hasVariants && row.image_source === 'url'" placeholder="https://ejemplo.com/filipina-blanca.webp"></label>
                <p class="text-sm text-red-700" x-show="row.error" x-text="row.error"></p>
                <template x-if="variantImage(row)"><img :src="variantImage(row)" :alt="row.color" class="h-32 w-32 rounded object-cover" referrerpolicy="no-referrer"></template>
            </div>
        </template>
        <button type="button" class="btn-secondary" @click="addVariant()" :disabled="variants.length >= 20">Añadir color</button>
        <p class="text-sm text-neva-muted">Hasta 20 colores. Los colores que quites se eliminarán al guardar. Puedes probarlos en la previsualización de la tarjeta.</p>
    </fieldset>
</section>

````

## tests/Feature/ImagePipelineTest.php

````php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Sector;
use App\Services\Catalog\CatalogPdf;
use App\Services\ImageProcessor;
use App\Services\RemoteImageDownloader;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    private function downloader(array $ips = ['93.184.215.14']): RemoteImageDownloader
    {
        return new class($ips) extends RemoteImageDownloader
        {
            public function __construct(private array $ips) {}

            protected function resolveAddresses(string $host): array
            {
                return $this->ips;
            }
        };
    }

    public function test_resize_both_formats_and_never_enlarge_small_images(): void
    {
        Storage::fake('public');
        foreach (['jpg', 'png', 'webp'] as $extension) {
            foreach ([1600, 240] as $width) {
                $file = UploadedFile::fake()->image('test.'.$extension, $width, 400);
                $base = app(ImageProcessor::class)->process($file);
                $this->assertTrue(ImageProcessor::isProcessed($base));
                foreach (['webp' => IMAGETYPE_WEBP, 'jpg' => IMAGETYPE_JPEG] as $format => $type) {
                    $info = getimagesize(Storage::disk('public')->path($base.'.'.$format));
                    $this->assertSame(min($width, 1000), $info[0]);
                    $this->assertSame($type, $info[2]);
                }
            }
        }
    }

    public function test_private_reserved_and_mixed_dns_are_blocked_before_http(): void
    {
        Http::fake();
        foreach (['http://localhost/x', 'http://127.0.0.1/x', 'http://10.1.2.3/x', 'http://192.168.1.1/x', 'http://169.254.169.254/x', 'http://[::1]/x', 'http://[fc00::1]/x', 'http://2130706433/x', 'file:///etc/passwd', 'https://user:pass@example.com/x'] as $url) {
            try {
                $this->downloader(['127.0.0.1'])->download($url);
                $this->fail('Se permitió una dirección privada: '.$url);
            } catch (RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        try {
            $this->downloader(['93.184.215.14', '192.168.0.1'])->download('https://example.com/x');
            $this->fail('Se permitió DNS mixto');
        } catch (RuntimeException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_redirects_are_revalidated_and_public_http_is_supported(): void
    {
        Http::fake(['https://example.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        try {
            $this->downloader()->download('https://example.com/image');
            $this->fail('Se permitió redirección privada');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('privadas', $e->getMessage());
        }
        Http::assertSentCount(1);
        Http::fake(['http://example.com/*' => Http::response('bytes', 200, ['Content-Type' => 'image/jpeg'])]);
        $this->assertSame('bytes', $this->downloader()->download('http://example.com/image'));
    }

    public function test_http_errors_html_and_oversized_responses_fail(): void
    {
        foreach ([Http::response('denied', 403), Http::response('<html>', 200, ['Content-Type' => 'text/html']), Http::response(str_repeat('x', RemoteImageDownloader::MAX_BYTES + 1), 200, ['Content-Type' => 'image/png'])] as $response) {
            Http::swap(new Factory);
            Http::fake(['https://example.com/*' => $response]);
            try {
                $this->downloader()->download('https://example.com/image');
                $this->fail('Respuesta inválida aceptada');
            } catch (RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_conversion_command_resumes_skips_completed_and_preserves_shared_original_until_unused(): void
    {
        Storage::fake('public');
        $product = Product::create(['name' => 'Demo', 'slug' => 'demo', 'short_description' => 'Prueba', 'reference_price' => 100]);
        $file = UploadedFile::fake()->image('legacy.png', 100, 100);
        Storage::disk('public')->put('products/legacy.png', file_get_contents($file->getPathname()));
        $first = $product->images()->create(['path' => 'products/legacy.png', 'alt' => 'Demo']);
        $second = $product->images()->create(['path' => 'products/legacy.png', 'alt' => 'Demo']);
        $this->artisan('images:convert', ['--limit' => 1])->assertSuccessful();
        Storage::disk('public')->assertExists('products/legacy.png');
        $firstPath = $first->fresh()->path;
        $this->artisan('images:convert')->assertSuccessful();
        Storage::disk('public')->assertMissing('products/legacy.png');
        $paths = Storage::disk('public')->allFiles();
        $this->assertCount(4, $paths);
        $this->artisan('images:convert')->assertSuccessful();
        $this->assertSame($firstPath, $first->fresh()->path);
        $this->assertSame($paths, Storage::disk('public')->allFiles());
        Storage::disk('public')->delete($second->fresh()->path.'.jpg');
        $this->artisan('images:convert')->assertSuccessful();
        $this->assertCount(4, Storage::disk('public')->allFiles());
    }

    public function test_failed_existing_url_is_preserved_for_retry(): void
    {
        Storage::fake('public');
        $product = Product::create(['name' => 'Demo', 'slug' => 'demo', 'short_description' => 'Prueba', 'reference_price' => 100]);
        $image = $product->images()->create(['path' => '', 'external_url' => 'http://127.0.0.1/x', 'alt' => 'Demo']);
        $this->artisan('images:convert')->assertFailed();
        $this->assertSame('http://127.0.0.1/x', $image->fresh()->external_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_demo_seed_creates_ready_pairs_and_is_idempotent(): void
    {
        Storage::fake('public');
        $this->seed(DemoProductSeeder::class);
        $this->assertDatabaseCount('products', 10);
        foreach (ProductImage::all() as $image) {
            $this->assertTrue(app(ImageProcessor::class)->complete($image->path));
        }
        $paths = Storage::disk('public')->allFiles();
        $this->seed(DemoProductSeeder::class);
        $this->assertSame($paths, Storage::disk('public')->allFiles());
    }

    public function test_timeout_and_invalid_image_bytes_do_not_leave_files(): void
    {
        Storage::fake('public');
        $this->app->instance(RemoteImageDownloader::class, $this->downloader());
        Http::fake(['https://example.com/*' => Http::failedConnection()]);
        try {
            app(ImageProcessor::class)->process('https://example.com/x', 99);
            $this->fail('Se esperaba fallo de conexión');
        } catch (ConnectionException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        Http::swap(new Factory);
        Http::fake(['https://example.com/*' => Http::response('fake jpeg', 200, ['Content-Type' => 'image/jpeg'])]);
        try {
            app(ImageProcessor::class)->process('https://example.com/x', 99);
            $this->fail('Se aceptaron bytes inválidos');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('válidas', $e->getMessage());
        }
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_failed_second_write_cleans_first_version(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->withArgs(fn ($path, $bytes) => str_ends_with($path, '.webp'))->andReturn(true);
        $disk->shouldReceive('put')->once()->withArgs(fn ($path, $bytes) => str_ends_with($path, '.jpg'))->andReturn(false);
        $disk->shouldReceive('delete')->once()->withArgs(fn ($paths) => count($paths) === 2)->andReturn(true);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $this->expectException(RuntimeException::class);
        app(ImageProcessor::class)->process(UploadedFile::fake()->image('photo.jpg'));
    }

    public function test_pdf_embeds_local_jpeg_without_http_and_handles_missing_file(): void
    {
        $public = storage_path('framework/testing/pdf-public');
        $this->app->usePublicPath($public);
        config(['filesystems.disks.public.root' => $public.'/storage']);
        Storage::forgetDisk('public');
        try {
            $base = app(ImageProcessor::class)->process(UploadedFile::fake()->image('sample.png', 300, 400));
            $product = Product::create(['name' => 'Demo PDF', 'slug' => 'demo-pdf', 'short_description' => 'Prueba', 'reference_price' => 100, 'is_available' => true]);
            $image = $product->images()->create(['path' => $base, 'alt' => 'Demo PDF', 'is_primary' => true]);
            $sector = Sector::create(['name' => 'Médico', 'slug' => 'medico', 'is_active' => true]);
            $product->sectors()->attach($sector);
            Http::fake();
            $service = app(CatalogPdf::class);
            $this->assertSame(realpath(public_path('storage/'.$base.'.jpg')), $service->data($sector)['photos'][$product->id]);
            $pdf = $service->render($sector);
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertStringContainsString('/Subtype /Image', $pdf);
            Http::assertNothingSent();
            Storage::disk('public')->delete($base.'.jpg');
            $data = $service->data($sector);
            $this->assertNull($data['photos'][$product->id]);
            $this->assertStringContainsString('Fotografía no disponible', view('pdf.sector-catalog', $data)->render());
            $this->assertStringStartsWith('%PDF-', $service->render($sector));
        } finally {
            File::deleteDirectory($public);
        }
    }
}

````

## tests/Feature/ProductImageTest.php

````php
<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\RemoteImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $file = $this->webp();
        $this->mock(RemoteImageDownloader::class)->shouldReceive('download')->andReturn(file_get_contents($file->getPathname()));
    }

    private function data(array $extra = []): array
    {
        return array_replace([
            'name' => 'Filipina azul', 'slug' => 'filipina-azul',
            'short_description' => 'Prenda de prueba', 'reference_price' => '400.00',
            'is_published' => '1', 'is_available' => '1', 'is_featured' => '0',
            'sectors' => [Sector::firstOrCreate(['slug' => 'clinico'], ['name' => 'Clínico'])->id],
        ], $extra);
    }

    private function webp(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('prenda.webp', base64_decode('UklGRjoAAABXRUJQVlA4IC4AAACQAQCdASoEAAQAAUAmJaACdLoAA5gA/vtV4/+lwf/S4P/pcH/pcH8bss4bpAAA'));
    }

    public function test_webp_is_stored_on_disk_preserved_and_replaced_by_external_link(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/productos', $this->data(['image_source' => 'file', 'image_file' => $this->webp()]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $image = $product->images()->firstOrFail();
        $path = $image->path;
        Storage::disk('public')->assertExists([$path.'.webp', $path.'.jpg']);
        $this->assertNull($image->external_url);
        $this->get('/productos')->assertSee($image->url);
        $this->get('/admin/productos/'.$product->id.'/edit')->assertOk()->assertSee('Así se verá en el catálogo');
        $this->put('/admin/productos/'.$product->id, $this->data(['image_source' => 'keep']))->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists([$path.'.webp', $path.'.jpg']);
        $this->assertDatabaseHas('product_images', ['id' => $image->id, 'path' => $path]);

        $this->put('/admin/productos/'.$product->id, $this->data([
            'image_source' => 'url', 'image_url' => 'https://drive.google.com/file/d/example_123/view?usp=sharing',
        ]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing([$path.'.webp', $path.'.jpg']);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertNull($image->fresh()->external_url);
        $this->assertNotSame($path, $image->fresh()->path);
        Storage::disk('public')->assertExists($image->fresh()->path.'.jpg');
        $this->get('/productos/filipina-azul')->assertOk()->assertSee($image->fresh()->url);
    }

    public function test_disallowed_invalid_and_oversized_files_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        foreach ([
            UploadedFile::fake()->createWithContent('invalid.gif', $png),
            UploadedFile::fake()->createWithContent('invalid.webp', 'not an image'),
            $this->webp()->size(10241),
        ] as $file) {
            $file->mimeType(mime_content_type($file->getPathname()));
            $this->post('/admin/productos', $this->data(['image_source' => 'file', 'image_file' => $file]))->assertSessionHasErrors('image_file');
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_image_sources_require_valid_input_and_remote_image_is_stored_locally(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/productos', $this->data(['image_source' => 'file']))->assertSessionHasErrors('image_file');
        foreach (['', 'javascript:alert(1)', ['invalid']] as $url) {
            $this->post('/admin/productos', $this->data(['image_source' => 'url', 'image_url' => $url]))->assertSessionHasErrors('image_url');
        }
        $url = 'https://images.example.com/prenda.webp?version=2';
        $this->post('/admin/productos', $this->data(['image_source' => 'url', 'image_url' => $url]))->assertSessionHasNoErrors();
        $image = Product::firstOrFail()->images()->firstOrFail();
        $this->assertNull($image->external_url);
        Storage::disk('public')->assertExists([$image->path.'.webp', $image->path.'.jpg']);
    }

    public function test_database_failure_cleans_new_file_without_removing_existing_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(ProductService::class);
        $product = $service->save(new Product, $this->data(['image_source' => 'file', 'image_file' => $this->webp()]), $admin);
        $oldPath = $product->images()->firstOrFail()->path;
        ActivityLog::creating(fn () => throw new RuntimeException('Simulated database failure'));
        try {
            $service->save($product, $this->data(['image_source' => 'file', 'image_file' => $this->webp()]), $admin);
            $this->fail('Expected rollback');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated database failure', $exception->getMessage());
        } finally {
            ActivityLog::flushEventListeners();
        }
        $this->assertEqualsCanonicalizing([$oldPath.'.webp', $oldPath.'.jpg'], Storage::disk('public')->allFiles());
        $this->assertDatabaseHas('product_images', ['path' => $oldPath]);
    }
}

````

## tests/Feature/RolesAndVariantsTest.php

````php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Services\RemoteImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RolesAndVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $bytes = base64_decode('UklGRjoAAABXRUJQVlA4IC4AAACQAQCdASoEAAQAAUAmJaACdLoAA5gA/vtV4/+lwf/S4P/pcH/pcH8bss4bpAAA');
        $this->mock(RemoteImageDownloader::class)->shouldReceive('download')->andReturn($bytes);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['name' => 'Filipina demo', 'slug' => 'filipina-demo', 'short_description' => 'Producto de prueba', 'reference_price' => '500.00', 'is_available' => 1, 'is_featured' => 0, 'sectors' => [Sector::firstOrCreate(['slug' => 'clinico'], ['name' => 'Clínico'])->id]], $extra);
    }

    public function test_publisher_can_manage_products_but_cannot_access_admin_features(): void
    {
        $publisher = User::factory()->create(['role' => 'publisher']);
        $this->post('/login', ['email' => $publisher->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($publisher);
        $this->get('/dashboard')->assertRedirect(route('admin.products.index'));
        $this->get('/admin/productos')->assertOk()->assertDontSee('Actividad')->assertDontSee('Cuentas');
        $this->get('/admin/productos/create')->assertOk()->assertDontSee('name="sku"', false);
        foreach (['/admin', '/admin/sectores', '/admin/sectores/create', '/admin/actividad', '/admin/estadisticas', '/admin/cuentas', '/profile'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/admin/productos', $this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 1);
        $this->post('/admin/sectores', ['name' => 'Otro'])->assertForbidden();
        $this->assertFalse(Schema::hasColumn('products', 'sku'));
    }

    public function test_only_admin_can_deactivate_accounts_and_data_is_preserved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $publisher = User::factory()->create(['role' => 'publisher']);
        $this->actingAs($publisher)->patch('/admin/cuentas/'.$admin->id, ['is_active' => 0])->assertForbidden();
        $this->actingAs($admin)->patch('/admin/cuentas/'.$publisher->id, ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($publisher->fresh()->is_active);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deactivated', 'entity_id' => $publisher->id]);
        $this->patch('/admin/cuentas/'.$admin->id, ['is_active' => 0])->assertSessionHasErrors('account');
        $this->actingAs($publisher->fresh())->get('/admin/productos')->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $publisher->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_colors_have_their_own_images_and_can_be_hidden_without_losing_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'publisher']));
        $rows = [
            ['color' => 'Blanco', 'color_hex' => '#ffffff', 'image_source' => 'url', 'image_url' => 'https://example.com/blanco.webp'],
            ['color' => 'Café', 'color_hex' => '#795548', 'image_source' => 'url', 'image_url' => 'https://example.com/cafe.webp'],
        ];
        $this->post('/admin/productos', $this->data(['has_variants' => 1, 'variants' => $rows, 'gallery_urls' => ['https://example.com/detalle.webp']]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertCount(2, $product->variants);
        $this->assertDatabaseCount('product_images', 3);
        $this->assertNull($product->variants->firstWhere('color', 'Café')->images->first()->external_url);
        $this->get('/productos/filipina-demo')->assertOk()->assertSee('Elige un color')->assertSee('Café');
        $this->get('/admin/productos/'.$product->id.'/edit')->assertOk();
        $this->put('/admin/productos/'.$product->id, $this->data(['has_variants' => 0]))->assertSessionHasNoErrors();
        $this->assertCount(2, $product->fresh()->variants);
        $this->get('/productos/filipina-demo')->assertOk()->assertDontSee('Elige un color')->assertDontSee('https://example.com/cafe.webp');
    }

    public function test_colors_require_photos_and_cannot_use_another_products_variant(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $row = ['color' => 'Blanco', 'color_hex' => '#ffffff', 'image_source' => 'keep'];
        $this->post('/admin/productos', $this->data(['has_variants' => 1, 'variants' => [$row]]))->assertSessionHasErrors('variants.0.image_source');
        $row['image_source'] = 'url';
        $row['image_url'] = 'https://example.com/blanco.webp';
        $this->post('/admin/productos', $this->data(['has_variants' => 1, 'variants' => [$row]]))->assertSessionHasNoErrors();
        $row['id'] = Product::first()->variants()->first()->id;
        $this->post('/admin/productos', $this->data(['slug' => 'otro', 'has_variants' => 1, 'variants' => [$row]]))->assertSessionHasErrors('variants.0.id');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_variant_upload_gallery_removal_and_color_replacement(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'publisher']));
        $file = fn () => UploadedFile::fake()->createWithContent('foto.webp', base64_decode('UklGRjoAAABXRUJQVlA4IC4AAACQAQCdASoEAAQAAUAmJaACdLoAA5gA/vtV4/+lwf/S4P/pcH/pcH8bss4bpAAA'));
        $this->post('/admin/productos', $this->data(['has_variants' => 1, 'variants' => [['color' => 'Blanco', 'color_hex' => '#ffffff', 'image_source' => 'file', 'image_file' => $file()]], 'gallery_files' => [$file(), $file()]]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $path = $variant->images()->first()->path;
        Storage::disk('public')->assertExists([$path.'.webp', $path.'.jpg']);
        $removed = $product->images()->whereNull('product_variant_id')->first();
        $this->put('/admin/productos/'.$product->id, $this->data(['has_variants' => 1, 'variants' => [['id' => $variant->id, 'color' => 'Café', 'color_hex' => '#795548', 'image_source' => 'url', 'image_url' => 'https://example.com/cafe.webp']], 'remove_images' => [$removed->id]]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing([$path.'.webp', $path.'.jpg']);
        Storage::disk('public')->assertMissing([$removed->path.'.webp', $removed->path.'.jpg']);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'color' => 'Café']);
        $this->assertDatabaseHas('product_images', ['product_variant_id' => $variant->id, 'external_url' => null]);
    }
}

````

## composer.json

````json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": ["laravel", "framework"],
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "bacon/bacon-qr-code": "3.*",
        "barryvdh/laravel-dompdf": "3.*",
        "intervention/image": "3.*",
        "intervention/image-laravel": "1.*",
        "laravel/framework": "^12.0",
        "laravel/tinker": "^2.10.1"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/breeze": "^2.4",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "laravel/sail": "^1.41",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^11.5.50"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}

````

## docs/architecture.md

````markdown
# Arquitectura MVC de Neva Medic

La aplicación usa Laravel 12, Eloquent, Blade, Tailwind CSS y Alpine.js.
El sitio público y la administración son páginas independientes. El diseño de la
landing V2 orienta los colores y la tipografía; no obliga a reunir el sistema en
una sola pantalla.

## Flujo de una solicitud

Ruta → middleware → controlador → modelo o servicio → vista Blade.
Los Form Requests autorizan y validan escrituras antes del controlador.
ProductService coordina la transacción entre producto, sectores, imágenes, colores y bitácora.
No se añade una capa de repositorios que duplique Eloquent.

## Organización

- app/Models: Product, Sector, ProductVariant, ProductImage, ProductDailyMetric,
  ActivityLog y User.
- app/Http/Controllers/Site: inicio, catálogo, ficha, personalización, contacto,
  catálogos y aviso de privacidad.
- app/Http/Controllers/Admin: resumen, productos, sectores, cuentas y reportes.
- app/Http/Controllers/Auth: autenticación Breeze existente.
- app/Http/Requests/Admin: validación y autorización de productos y sectores.
- app/Http/Middleware/EnsureAdministrator: exige cuenta administrativa activa.
- app/Http/Middleware/EnsureCatalogAccess: permite administradores y publicadores activos.
- app/Services/Catalog/ProductService: operaciones transaccionales del catálogo.
- resources/views/layouts: layouts público, administrativo y de autenticación.
- resources/views/site y resources/views/admin: vistas por área y recurso.
- resources/views/components/catalog y components/ui: piezas reutilizables.
- routes/web.php: rutas públicas y perfil; routes/admin.php: rutas privadas.
- config/neva.php: identidad y canal de contacto.
- database/migrations: esquema versionado, compatible con MySQL y SQLite.
- tests/Feature/CatalogArchitectureTest.php: flujo, permisos y aislamiento público.

## Relaciones y convenciones

Producto ↔ Sector es muchos a muchos mediante product_sector.
Producto tiene variantes, imágenes y métricas diarias. Una imagen puede asociarse
a una variante de color. La validación exige que los IDs de colores y fotografías
pertenezcan al producto editado. Cada color necesita una fotografía.
La moneda inicial es MXN y el precio es decimal, no un cálculo con flotantes.
Los productos se archivan con SoftDeletes. La disponibilidad es el único control
de visibilidad: is_available=true muestra el producto; false lo oculta de inicio,
listado, conteos y ficha pública. La columna heredada is_published se sincroniza
al guardar por compatibilidad, pero no decide la visibilidad ni aparece en el formulario.
Las fichas públicas resuelven por slug; administración
resuelve por id, para permitir cambiar slugs sin romper rutas de edición.
Las métricas tienen una fila única por producto y fecha, sin identificar visitantes.

## Estado implementado

- Navegación y vistas adaptables a móvil y escritorio.
- Buscador de categorías en el formulario, con selección mediante ratón o teclado.
- Tallas predefinidas en config/neva.php: el formulario selecciona existencias y
  products.available_sizes las guarda como una lista JSON. Se pueden marcar o
  desmarcar todas; la ficha solo muestra las seleccionadas. Son tallas generales
  del producto, no cantidades de inventario ni existencias por combinación de color.
  Los productos existentes comienzan sin tallas asignadas para no inventar existencias.
- Cuidados es opcional. El menú incluye iconos y el cierre de sesión está separado
  en la esquina superior derecha del contenido, tanto para admin como publicador.
- Listado público, filtros, paginación, destacados y ficha.
- Menú Productos por sectores activos y tipos de prenda, con filtro combinado.
  Los tipos y su agrupación inicial están en config/neva.php; el formulario de
  producto guarda product_type. Los productos anteriores conservan su contenido
  y pueden clasificarse desde edición. Los sectores nuevos tienen enlace propio;
  sus tipos se agregan en sector_types de la configuración.
- Registro público deshabilitado.
- Inicio de sesión para administradores y publicadores activos.
- El publicador accede únicamente a productos y su formulario. Las rutas de
  cuentas, sectores, estadísticas, actividad, resumen y perfil requieren administrador.
- Cuentas sin eliminación: solo el administrador desactiva o reactiva cuentas.
  No puede desactivarse a sí mismo. Se revocan sesiones en base de datos y el
  token de recordar sesión; los middleware también rechazan cuentas inactivas.
- CRUD de productos con archivado, sectores múltiples y vista previa privada.
- Creación y edición de sectores; desactivar conserva sus relaciones.
- Bitácora transaccional de modificaciones de productos.
- Imagen principal, galería y colores desde JPG, PNG o WebP (máximo 10 MB)
  o enlace HTTP/HTTPS público. ImageProcessor descarga con protección SSRF,
  reduce a 1000 px de ancho sin ampliar y escribe WebP/JPEG con calidad 80.
  product_images.path guarda la ruta base sin extensión; external_url solo
  conserva fuentes heredadas pendientes de conversión. Drive requiere acceso público.
  El formulario muestra una tarjeta en vivo y permite conservar o reemplazar
  la imagen; el reemplazo elimina el archivo anterior después de guardar.
- Listado de métricas existentes, sin generar cifras de muestra.
- Miniaturas junto a los productos en administración.
- Galería adicional y colores editables en el mismo formulario. Desactivar
  has_variants conserva los colores; quitar una fila al guardar sí elimina ese
  color y su foto. Al seleccionar un color cambia la imagen de la ficha.
- Contacto incluye mapa general de Torreón, sin señalar un establecimiento.

## Correspondencia del formulario con la base de datos

- products: name, slug, product_type, short_description, description, material,
  care_instructions, reference_price, is_available, is_featured, has_variants, available_sizes.
  SKU se eliminó mediante migración. currency se fija en MXN.
- Sectores: IDs relacionados en product_sector, no texto duplicado en products.
- Fotos: product_images.path sin extensión, is_primary para portada y
  product_variant_id para la imagen vinculada al color. Nunca se guardan binarios.
- Colores: product_variants.color y color_hex. size y design son columnas
  heredadas sin uso en el formulario de variantes; se guardan vacías.
- Cuentas: users.role admite admin y publisher; is_active controla acceso.
  is_admin se conserva para compatibilidad con cuentas y pruebas anteriores.
  Los campos de rol y actividad no son asignables desde el formulario de perfil.
- image_source, gallery_files, gallery_urls y remove_images son instrucciones
  de formulario que el servicio traduce a registros y archivos; no requieren
  columnas con esos nombres.

## Extensiones pendientes

La ordenación manual de la galería sigue pendiente.
Los catálogos se generan con Dompdf por sector y leen JPEG locales previamente
procesados. No descargan ni convierten fotos al generar el PDF. Los fallos se
registran y muestran un placeholder. Ver docs/image-processing.md.
La captura de vistas y clics sigue pendiente; debe ser agregada, atómica y con
limitación de abuso. El aviso de privacidad requiere contenido final del cliente.
La bitácora actual cubre productos y activación de cuentas; ampliar a sectores y accesos cuando se
implemente esa parte del alcance.
El contacto usa NEVA_WHATSAPP solo cuando tiene formato internacional válido.
Sin configuración muestra un estado pendiente y no enlaza a un número inventado.

## Preparación local

Con MySQL de XAMPP encendido y la conexión .env configurada:

    php artisan migrate
    php artisan db:seed --class=SectorSeeder
    php artisan neva:admin correo-del-administrador@dominio.com
    npm run build
    php artisan serve

El comando neva:admin habilita una cuenta existente o solicita nombre y contraseña
para crearla. Se permiten varios administradores. Para crear ambos roles:

    php artisan neva:account correo@dominio.com admin --name="Nombre"
    php artisan neva:account correo@dominio.com publisher --name="Nombre"

Solicita contraseña oculta. --generate-password genera una contraseña y la muestra
una sola vez. Las cuentas existentes no cambian sin --reset-password.

La precarga opcional se ejecuta con php artisan db:seed --class=DemoProductSeeder.
Crea diez productos marcados Demo y veinte ilustraciones WebP originales, con
precios de ejemplo. No reemplaza ni duplica productos existentes. Los archivos
fuente están en database/demo-images y el generador en scripts/generate_demo_assets.py.

En cada instalación ejecutar php artisan storage:link para servir fotografías
públicas. No almacenar archivos subidos directamente bajo public.

Para probar:

    php artisan config:clear
    php artisan test

Las pruebas usan SQLite en memoria configurado en phpunit.xml. No deben apuntar a
la base de desarrollo. Nunca usar migrate:fresh sobre datos del cliente.

## Crecimiento

Agregar controladores y Form Requests por recurso (variantes, imágenes, PDFs).
Extraer servicios cuando exista una operación de negocio que coordine modelos,
archivos o trabajos de cola. Incorporar Jobs para catálogos pesados o
procesamiento de imágenes cuando haga falta, sin introducir hoy colas vacías ni
dependencias no usadas. Los dos roles actuales se autorizan en middleware y
Form Requests. Añadir Policies si se requiere que cada publicador solo modifique
sus propios productos; actualmente comparten el catálogo.

````

## docs/image-processing.md

````markdown
# Imágenes de productos: WebP para web y JPEG para PDF

Implementado en Laravel 12, Intervention Image 3.11.9, integración Laravel 1.5.9,
driver GD y Dompdf 3.1.6. Se conserva MVC: el controlador existente delega
en ProductService y este utiliza ImageProcessor para principal, galería y colores.

## Datos y archivos

No se necesitan nuevas columnas ni migraciones de esquema. `product_images.path`
guarda `products/UUID`, sin extensión. `external_url` queda en NULL después de
convertir; las fuentes heredadas que fallen permanecen intactas para reintentar.
Nunca se almacenan binarios ni Base64 en la BD.

Por imagen se guardan exclusivamente `products/UUID.webp` y `products/UUID.jpg`
en el disco public. Ambos usan calidad 80 y ancho máximo 1000 px, manteniendo la
proporción y sin ampliar. GD usa fondo blanco al exportar JPEG con transparencia.
Se aceptan JPG/JPEG, PNG y WebP, máximo 10 MB; también se limita a 20 megapíxeles
para evitar agotar memoria al descomprimir archivos pequeños de dimensiones enormes.

El reemplazo limpia ambas versiones anteriores después de confirmar la BD,
si ninguna otra imagen utiliza esa ruta. Los archivos nuevos se eliminan si falla
la escritura de la segunda versión o la transacción. Los productos archivados
conservan sus imágenes porque el archivado es reversible.

Los originales importados no se conservan en el disco public una vez que dejan
de estar referenciados. Las ilustraciones fuente de database/demo-images son
recursos del seeder, no duplicados creados al generar PDFs.

## URLs y errores

RemoteImageDownloader acepta HTTP/HTTPS públicos, puertos 80/443 y sin credenciales.
Valida todas las IPs DNS, rechaza redes privadas/reservadas, fija la IP de conexión
con CURLOPT_RESOLVE y desactiva proxies de entorno. Revalida cada redirección
(máximo cuatro) para impedir SSRF y DNS rebinding.
Cada petición tiene timeout de 10 segundos y conexión de 5 segundos. No realiza
reintentos automáticos. Una cadena de redirecciones puede sumar varios timeouts.
Comprueba estado 2xx, Content-Type image/, tamaño durante la transferencia y
formato real antes de decodificar. La descarga usa un temporal eliminado al cerrar.
Un enlace de Drive que devuelva HTML, requiera login o responda 403 se rechaza;
un User-Agent no garantiza acceso a recursos privados.

El formulario muestra el error y no guarda cambios parciales. Los fallos de
procesamiento, migración, lectura PDF y limpieza se registran con Log::warning,
producto/imagen cuando corresponda y causa. Consultar storage/logs/laravel.log
o el canal configurado en Laravel.

## Dompdf

CatalogImage resuelve `public_path('storage/'.$image->path.'.jpg')` y comprueba
existencia, lectura, carpeta y formato JPEG. La plantilla existente recibe esa
ruta absoluta en `$photos`; si es NULL muestra el placeholder existente.
CatalogPdf permite public y storage/app/public mediante chroot, con red remota
desactivada. Generar el PDF no invoca el procesador ni realiza descargas.
El QR continúa embebido en el documento; tampoco se guarda en la BD.

## Instalación y ejecución

Desde la raíz del proyecto, con GD, cURL y MySQL activos:

```powershell
composer install
php artisan config:clear
php artisan storage:link
php artisan images:convert
npm run build
```

Si storage:link indica que el enlace ya existe, no es necesario recrearlo si
apunta correctamente a storage/app/public. Para esta instalación ya se verificó.
Si npm falla por la configuración local de NVM, la compilación equivalente es:

```powershell
node node_modules/vite/bin/vite.js build
```

El comando procesa por ID, en lotes de lectura de 50 registros. Cada fila se
confirma individualmente. No requiere cursor: al repetirlo omite las rutas base
que ya tienen las dos versiones. Si falta una versión, reconstruye el par usando
la restante. Si faltan ambas y ya no existe el original, informa el fallo y debe
subirse de nuevo la fotografía. Un fallo no detiene los otros registros; el
código de salida es 1 si hubo errores y 0 si todos se procesaron/omitieron.

```powershell
# Limitar una ejecución a 20 conversiones o intentos fallidos
php artisan images:convert --limit=20
# Procesar solo las imágenes de un producto
php artisan images:convert --product=12
# Reanudar después de una interrupción
php artisan images:convert
```

Se usa el mismo bloqueo del producto que en edición para no sobrescribir una
imagen recién reemplazada. La ruta se actualiza únicamente tras guardar ambos
archivos. Un cierre forzado del proceso justo después de escribir y antes de
confirmar puede dejar archivos huérfanos; la limpieza por excepciones no puede
ejecutarse cuando se apaga la computadora. El comando no elimina indiscriminadamente
archivos no referenciados porque podrían pertenecer a una operación en curso.

PHP local tiene upload_max_filesize=40M, post_max_size=40M y max_file_uploads=20.
El límite de la aplicación es 10 MB por foto, pero un formulario con varias fotos
tiene además el límite total de PHP. Para cargas grandes, dividirlas entre varias
ediciones o ajustar esos límites en el servidor de destino y reiniciar PHP.

## Validación realizada

- Formatos reales, tamaño, dimensiones, dos archivos, reemplazo y rollback.
- URLs privadas, IPv6, DNS mixto, redirecciones internas, HTML, 403 y conexión fallida.
- Conversión reanudable, rutas compartidas y reparación de una versión ausente.
- PDF con JPEG local, sin llamadas HTTP; archivo ausente produce placeholder.
- Seeder de demostración genera directamente los pares y sigue siendo idempotente.

```powershell
php artisan test
```

En la base local se convirtieron 33 imágenes con cero fallos. La segunda ejecución
omitió las 33 sin generar nuevas versiones. Se revisaron visualmente las seis
páginas del PDF industrial: las cuatro fotografías, portada y QR se muestran.
El PDF de prueba contiene URLs localhost; al publicar debe configurarse APP_URL
con el dominio público real antes de generar documentos para compartir.

Referencias: [Intervention v3](https://image.intervention.io/v3/getting-started/frameworks),
[scaleDown](https://image.intervention.io/v3/modifying-images/resizing),
[Dompdf](https://github.com/dompdf/dompdf#resource-reference-requirements).

````

