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
