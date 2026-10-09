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
