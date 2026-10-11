<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        $sector = Sector::firstOrCreate(['slug' => 'clinico'], ['name' => 'Clínico']);

        return array_replace([
            'name' => 'Filipina de prueba', 'slug' => 'filipina-prueba',
            'product_type' => 'filipinas',
            'short_description' => 'Descripción verificable', 'reference_price' => '590.00',
            'is_published' => '1', 'is_featured' => '1', 'is_available' => '1',
            'sectors' => [$sector->id],
        ], $overrides);
    }

    public function test_public_pages_render_without_catalog_content(): void
    {
        foreach (['/', '/productos', '/personalizacion', '/catalogos', '/contacto', '/aviso-de-privacidad'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_routes_require_an_active_administrator(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => false]))->get('/admin/productos')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/productos', $this->data())->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_only_active_admin_can_log_in(): void
    {
        foreach ([['is_admin' => false], ['is_admin' => true, 'is_active' => false]] as $flags) {
            $user = User::factory()->create($flags);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_product_lifecycle_sector_sync_preview_and_audit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/productos', $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertCount(1, $product->sectors);
        $this->assertSame('filipinas', $product->product_type);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'entity_id' => $product->id]);
        $this->get('/productos/'.$product->slug)->assertOk()->assertSee('Filipina de prueba');
        $this->get('/productos?sector=clinico&q=Filipina')->assertOk()->assertSee('Filipina de prueba');
        $this->get('/productos?sector=clinico&type=filipinas')->assertOk()->assertSee('Filipina de prueba');
        $this->get('/productos?sector=clinico&type=overoles')->assertOk()->assertDontSee('Filipina de prueba');
        $this->get('/')->assertSee(route('products.index', ['sector' => 'clinico', 'type' => 'filipinas']));

        foreach (['/admin', '/admin/productos', '/admin/productos/create', '/admin/productos/'.$product->id.'/edit', '/admin/sectores', '/admin/sectores/create', '/admin/estadisticas', '/admin/actividad'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->put('/admin/productos/'.$product->id, $this->data(['is_available' => '0']))->assertSessionHasNoErrors();
        $this->get('/productos/'.$product->slug)->assertNotFound();
        $this->get('/admin/productos/'.$product->id.'/vista-previa')->assertOk()->assertSee('Vista previa');
        $this->delete('/admin/productos/'.$product->id)->assertRedirect(route('admin.products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->get('/productos/'.$product->slug)->assertNotFound();
        $this->assertEquals(3, ActivityLog::where('entity_type', 'product')->count());
    }

    public function test_invalid_product_does_not_write_partial_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/productos', $this->data(['sectors' => [99999], 'reference_price' => '-10', 'product_type' => 'invalid']))->assertSessionHasErrors(['sectors.0', 'reference_price', 'product_type']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_sector_creation_and_update(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/sectores', ['name' => 'Industrial', 'slug' => 'industrial', 'is_active' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.sectors.index'));
        $sector = Sector::firstOrFail();
        $this->get('/admin/sectores/'.$sector->id.'/edit')->assertOk();
        $this->put('/admin/sectores/'.$sector->id, ['name' => 'Industrial', 'slug' => 'industrial', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($sector->fresh()->is_active);
    }

    public function test_availability_alone_controls_all_public_visibility(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $data = $this->data();
        unset($data['is_published']);
        $this->actingAs($admin)->post('/admin/productos', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertTrue($product->is_published);
        $this->get('/admin/productos/create')->assertOk()->assertDontSee('name="is_published"', false)->assertSee('Nombre para el enlace del producto')->assertSee('Buscar categoría');

        // Legacy drafts that are available now follow the same single-control rule.
        $product->update(['is_published' => false]);
        $this->get('/productos/'.$product->slug)->assertOk();
        $this->get('/')->assertSee($product->name);
        $this->get('/productos?type=filipinas')->assertSee($product->name);

        $this->put('/admin/productos/'.$product->id, array_replace($data, ['is_available' => '0', 'is_published' => '1']))->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->is_published);
        // Even an old inconsistent flag must never expose an unavailable product.
        $product->update(['is_published' => true]);
        $this->get('/')->assertDontSee($product->name);
        $this->get('/productos?sector=clinico&type=filipinas&available=0')->assertDontSee($product->name);
        $this->get('/productos/'.$product->slug)->assertNotFound();
        $this->get('/catalogos')->assertViewHas('sectors', fn ($sectors) => $sectors->first()->products_count === 0);
        $this->get('/admin/productos/'.$product->id.'/vista-previa')->assertOk();
        $this->put('/admin/productos/'.$product->id, $data)->assertSessionHasNoErrors();
        $this->get('/productos/'.$product->slug)->assertOk();
    }

    public function test_inactive_sector_is_not_a_public_filter(): void
    {
        $data = $this->data();
        $product = Product::create(collect($data)->except('sectors')->all());
        $product->sectors()->sync($data['sectors']);
        Sector::first()->update(['is_active' => false]);
        $this->get('/productos?sector=clinico')->assertOk()->assertDontSee($product->name);
    }
}
