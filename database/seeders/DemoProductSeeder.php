<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            DB::transaction(function () use ($slug, $name, $type, $sector, $price, $colors) {
                $product = Product::create(['slug' => 'demo-'.$slug, 'name' => $name.' · Demo', 'product_type' => $type, 'short_description' => 'Producto de demostración con dos colores. Imagen y precio de ejemplo.', 'description' => 'Muestra para probar el catálogo y la selección de colores; no representa existencias ni especificaciones finales del cliente.', 'reference_price' => $price, 'currency' => 'MXN', 'is_available' => true, 'is_published' => true, 'is_featured' => in_array($slug, ['filipina-esencial', 'conjunto-quirurgico', 'camisa-industrial']), 'has_variants' => true]);
                $product->sectors()->sync([Sector::where('slug', $sector)->firstOrFail()->id]);
                foreach ($colors as $index => [$key, $label, $hex]) {
                    $filename = $slug.'-'.$key.'.webp';
                    $source = database_path('demo-images/'.$filename);
                    if (! is_file($source)) {
                        throw new \RuntimeException('Falta la ilustración '.$filename);
                    }
                    $path = 'demo/'.$filename;
                    if (! Storage::disk('public')->put($path, file_get_contents($source))) {
                        throw new \RuntimeException('No se pudo guardar '.$filename);
                    }
                    if ($index === 0) {
                        $product->images()->create(['path' => $path, 'alt' => $name.' · Muestra', 'is_primary' => true]);
                    }
                    $variant = $product->variants()->create(['color' => $label, 'color_hex' => $hex, 'size' => '', 'is_available' => true]);
                    $product->images()->create(['path' => $path, 'alt' => $name.' '.$label.' · Muestra', 'product_variant_id' => $variant->id]);
                }
            });
        }
    }
}
