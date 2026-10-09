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
