<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSizesTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $extra = []): array
    {
        return array_replace([
            'name' => 'Filipina con tallas', 'slug' => 'filipina-tallas',
            'short_description' => 'Producto de prueba', 'reference_price' => '450.00',
            'is_available' => 1, 'is_featured' => 0, 'sizes_present' => 1,
            'sectors' => [Sector::firstOrCreate(['slug' => 'clinico'], ['name' => 'Clínico'])->id],
        ], $extra);
    }

    public function test_sizes_can_be_selected_updated_and_cleared_with_optional_care(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'publisher']));
        $this->post('/admin/productos', $this->data(['available_sizes' => ['XS', 'M', 'XL'], 'care_instructions' => '']))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertSame(['XS', 'M', 'XL'], $product->available_sizes);
        $this->assertNull($product->care_instructions);
        $this->get('/productos/filipina-tallas')->assertOk()->assertSee('Tallas disponibles')->assertSee('data-size="XS"', false)->assertDontSee('data-size="XXL"', false);
        $this->get('/admin/productos/'.$product->id.'/edit')->assertOk()->assertSee('Cuidados (opcional)')->assertSee('Marcar todas');
        $this->put('/admin/productos/'.$product->id, $this->data(['available_sizes' => ['G', 'L', 'XXL']]))->assertSessionHasNoErrors();
        $this->assertSame(['G', 'L', 'XXL'], $product->fresh()->available_sizes);
        $this->put('/admin/productos/'.$product->id, $this->data())->assertSessionHasNoErrors();
        $this->assertSame([], $product->fresh()->available_sizes);
        $this->get('/productos/filipina-tallas')->assertDontSee('Tallas disponibles');
    }

    public function test_unknown_and_duplicate_sizes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/admin/productos', $this->data(['available_sizes' => ['inventada']]))->assertSessionHasErrors('available_sizes.0');
        $this->post('/admin/productos', $this->data(['available_sizes' => ['M', 'M']]))->assertSessionHasErrors('available_sizes.0');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_quote_preserves_only_available_product_options(): void
    {
        $product = Product::create(['name' => 'Filipina', 'slug' => 'filipina', 'short_description' => 'Prenda clínica', 'reference_price' => 450, 'is_available' => true, 'has_variants' => true, 'available_sizes' => ['XS', 'M']]);
        $product->variants()->create(['color' => 'Azul', 'size' => '', 'design' => '', 'is_available' => true]);
        $this->get('/contacto?product=filipina&size=M&color=Azul')->assertOk()->assertSee('Talla: M')->assertSee('Color: Azul');
        $this->get('/contacto?product=filipina&size=XXL&color=Rojo')->assertOk()->assertDontSee('Talla: XXL')->assertDontSee('Color: Rojo');
        $product->update(['is_available' => false]);
        $this->get('/contacto?product=filipina&size=M&color=Azul')->assertOk()->assertDontSee('Tu selección:');
    }
}
