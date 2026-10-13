<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomizationQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $product = Product::create(['name' => 'Filipina & confort', 'slug' => 'filipina', 'short_description' => 'Prenda', 'reference_price' => 490, 'is_available' => true, 'has_variants' => true, 'available_sizes' => ['S', 'M']]);
        $product->variants()->create(['color' => 'Café', 'size' => '', 'is_available' => true]);
        config(['neva.whatsapp' => '526181827551']);

        return $product;
    }

    public function test_form_inherits_selection_and_supports_general_advice(): void
    {
        $this->product();
        $this->get('/personalizacion?product=filipina&color=Caf%C3%A9&size=M')->assertOk()->assertViewHas('initial', fn ($data) => $data['product'] === 'filipina' && $data['color'] === 'Café' && $data['sizes'] === ['M' => 1] && ! $data['unknown']);
        $this->get('/personalizacion')->assertOk()->assertViewHas('initial', fn ($data) => $data['product'] === '' && $data['unknown']);
        $this->get('/personalizacion?product=filipina&size=XXL&color=Rojo')->assertViewHas('initial', fn ($data) => $data['sizes'] === [] && $data['color'] === '' && $data['unknown']);
    }

    public function test_multiple_sizes_logo_and_notes_are_encoded_in_whatsapp_message(): void
    {
        $this->product();
        $response = $this->post('/personalizacion/cotizar', ['product' => 'filipina', 'color' => 'Café', 'unknown_sizes' => 0, 'sizes' => ['S' => 5, 'M' => 10], 'logo' => 'yes', 'institution' => 'Escuela A & B', 'notes' => 'Bordado en pecho; nombre María.']);
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/526181827551?text=', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        foreach (['Filipina & confort', 'Color: Café', '15 prendas', 'S: 5, M: 10', 'Escuela A & B', 'María', 'compartiré el archivo por WhatsApp', route('products.show', 'filipina')] as $text) {
            $this->assertStringContainsString($text, $query['text']);
        }
        $this->assertArrayNotHasKey('B', $query);
    }

    public function test_unknown_sizes_ignore_stale_sizes_and_logo_name_and_allow_optional_quantity(): void
    {
        $this->product();
        $response = $this->post('/personalizacion/cotizar', ['product' => 'filipina', 'unknown_sizes' => 1, 'sizes' => ['XXL' => 99], 'quantity' => 20, 'logo' => 'no', 'institution' => 'Oculta']);
        $message = rawurldecode($response->headers->get('Location'));
        $this->assertStringContainsString('20 prendas', $message);
        $this->assertStringContainsString('aún no sé las tallas', $message);
        $this->assertStringNotContainsString('XXL', $message);
        $this->assertStringNotContainsString('Oculta', $message);
        $this->post('/personalizacion/cotizar', ['unknown_sizes' => 1, 'logo' => 'advice'])->assertRedirect();
    }

    public function test_invalid_options_and_totals_do_not_open_whatsapp(): void
    {
        $product = $this->product();
        $base = ['product' => 'filipina', 'unknown_sizes' => 0, 'sizes' => ['M' => 2], 'logo' => 'no'];
        foreach ([['color' => 'Rojo'], ['sizes' => ['XXL' => 2]], ['sizes' => ['S' => 6000, 'M' => 5000]], ['sizes' => []], ['sizes' => ['M' => -1]], ['sizes' => ['M' => 1.5]]] as $invalid) {
            $this->from('/personalizacion')->post('/personalizacion/cotizar', array_replace($base, $invalid))->assertRedirect('/personalizacion')->assertSessionHasErrors();
        }
        $product->update(['is_available' => false]);
        $this->post('/personalizacion/cotizar', $base)->assertSessionHasErrors('product');
        $product->delete();
        $this->post('/personalizacion/cotizar', $base)->assertSessionHasErrors('product');
    }
}
