<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PresentationProductSeeder extends Seeder
{
    public function run(): void
    {
        // Copy for a client demonstration, not confirmed manufacturing specifications.
        $items = [
            'demo-filipina-esencial' => ['Filipina de uso diario para integrar el uniforme de consultorios y equipos de atención.', 'Una opción de estilo sencillo para la presentación del personal clínico. Combina con pantalones del catálogo y permite explorar los colores cargados para este modelo.', 'Mezcla de poliéster y algodón', ['XS', 'S', 'M', 'L', 'XL']],
            'demo-filipina-balance' => ['Filipina en tonos sobrios para coordinar la imagen de tu equipo clínico.', 'Modelo pensado como parte de una propuesta de uniforme institucional. Consulta las vistas en azul marino y verde y selecciona la talla para preparar tu cotización.', 'Mezcla de poliéster y algodón', ['S', 'M', 'L', 'XL', 'XXL']],
            'demo-bata-clinica' => ['Bata para complementar la presentación profesional en consultorios y áreas de atención.', 'Una prenda para integrar al uniforme clínico de tu institución. Revisa sus opciones de color y consulta la posibilidad de incorporar nombre o identidad institucional mediante cotización.', 'Gabardina ligera de poliéster y algodón', ['XS', 'S', 'M', 'L', 'XL']],
            'demo-pantalon-clinico' => ['Pantalón clínico en colores neutros para combinar con filipinas y conjuntos.', 'Completa el uniforme de tu equipo con una propuesta de pantalón en azul marino o negro. Puedes cotizarlo por separado y consultar la combinación con otros modelos del catálogo.', 'Mezcla de poliéster y algodón', ['S', 'M', 'L', 'XL', 'XXL']],
            'demo-gorro-clinico' => ['Gorro textil para coordinar con el uniforme clínico en azul o verde.', 'Accesorio para completar la imagen del equipo. Explora los colores disponibles en esta muestra y solicita asesoría sobre ajuste, medidas y opciones de personalización.', 'Tela de algodón y poliéster', ['Única']],
            'demo-conjunto-quirurgico' => ['Propuesta de filipina y pantalón coordinados para el uniforme del personal médico.', 'Conjunto para presentar una imagen uniforme en equipos de atención. Revisa los colores azul marino y café y consulta las medidas de ambas prendas antes de confirmar tu pedido.', 'Mezcla de poliéster y algodón', ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
            'demo-bata-medica' => ['Bata médica de estilo clásico para consultas y presentación institucional.', 'Complementa el uniforme profesional con una bata en blanco o gris. Consulta tallas, medidas y bordado de nombre o logotipo para desarrollar una propuesta acorde con tu institución.', 'Gabardina de poliéster y algodón', ['S', 'M', 'L', 'XL', 'XXL']],
            'demo-camisa-industrial' => ['Camisa de trabajo para coordinar la imagen del personal operativo y de servicio.', 'Una propuesta de uniforme en azul o gris para equipos de trabajo. Consulta las medidas del modelo y las opciones de bordado para identificar a tu empresa o área de servicio.', 'Gabardina de algodón y poliéster', ['S', 'M', 'L', 'XL', 'XXL']],
            'demo-overol-industrial' => ['Overol de trabajo para una presentación coordinada del equipo operativo.', 'Prenda de cuerpo completo en azul marino o gris para una propuesta de uniforme industrial. Solicita asesoría sobre talla y medidas antes de cotizar la dotación de tu equipo.', 'Gabardina de algodón y poliéster', ['S', 'M', 'L', 'XL', 'XXL']],
            'demo-chaleco-seguridad' => ['Chaleco en naranja o amarillo para complementar el uniforme del equipo de trabajo.', 'Consulta las imágenes de cada color y coordina esta prenda con el resto del uniforme industrial. Las especificaciones de protección o certificaciones, si se requieren para tu actividad, deben validarse antes de la compra.', 'Tejido de poliéster', ['M', 'L', 'XL', 'XXL']],
        ];
        $care = "Lavar con agua fría y colores similares.\nUsar detergente suave y evitar blanqueadores.\nSecar a la sombra y seguir las indicaciones de la etiqueta de la prenda.";
        $service = app(ProductService::class);
        $changed = 0;
        DB::transaction(function () use ($items, $care, $service, &$changed) {
            $products = Product::whereIn('slug', [...array_keys($items), 'filipina-azul', 'pantalon-cargo-industrial'])->lockForUpdate()->get();
            $backup = $products->map(fn ($product) => $service->snapshot($product))->all();
            $backupPath = 'presentation-backups/products-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
            if (! Storage::disk('local')->put($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('No se pudo guardar la copia previa; no se modificaron productos.');
            }
            $this->command?->info('Copia previa: '.Storage::disk('local')->path($backupPath));
            foreach ($products as $product) {
                $before = $service->snapshot($product);
                $changes = [];
                if (isset($items[$product->slug])) {
                    [$summary, $description, $material, $sizes] = $items[$product->slug];
                    if (blank($product->short_description) || $product->short_description === 'Producto de demostración con dos colores. Imagen y precio de ejemplo.') {
                        $changes['short_description'] = $summary;
                    }
                    if (blank($product->description) || $product->description === 'Muestra para probar el catálogo y la selección de colores; no representa existencias ni especificaciones finales del cliente.') {
                        $changes['description'] = $description."\n\nFicha de muestra para presentación: materiales, tallas y precios sujetos a confirmación.";
                    }
                    if (blank($product->material)) $changes['material'] = $material;
                    if (blank($product->care_instructions)) $changes['care_instructions'] = $care;
                    if (blank($product->available_sizes)) $changes['available_sizes'] = $sizes;
                } elseif ($product->slug === 'filipina-azul') {
                    if (blank($product->material)) $changes['material'] = 'Mezcla de poliéster y algodón (propuesta de muestra)';
                    if (blank($product->available_sizes)) $changes['available_sizes'] = ['XS', 'S', 'M', 'L', 'XL'];
                    if ($product->description === 'facil uso') $changes['description'] = 'Blusa de uniforme para mujer con cuello en V y color sólido. Una opción para coordinar la presentación del personal clínico y médico. Consulta medidas y personalización al solicitar tu cotización.';
                    if ($product->short_description === 'Blusa para mujer') $changes['short_description'] = 'Blusa de uniforme para mujer con cuello en V, para coordinar el equipo clínico y médico.';
                    if ($product->care_instructions === 'no lavar con agua aliente') $changes['care_instructions'] = 'No lavar con agua caliente. Seguir las indicaciones de la etiqueta de la prenda.';
                } elseif ($product->slug === 'pantalon-cargo-industrial') {
                    if (blank($product->available_sizes)) $changes['available_sizes'] = ['S', 'M', 'L', 'XL', 'XXL'];
                    if ($product->product_type === 'pantalones') $changes['product_type'] = 'pantalones-trabajo';
                    if ($product->name === 'Pantalon Industrial') $changes['name'] = 'Pantalón industrial tipo cargo';
                }
                $product->fill($changes);
                if (! $product->isDirty()) continue;
                $fields = array_keys($product->getDirty());
                $product->save();
                ActivityLog::record(null, 'updated', 'product', $product->id, $before, $service->snapshot($product), [
                    'reason' => 'client_presentation_sample_content', 'sample_data' => true,
                    'changed_fields' => $fields, 'backup' => $backupPath,
                ]);
                $changed++;
            }
        });
        $this->command?->info("Productos completados: {$changed}. Imágenes, precios, colores y disponibilidad conservados.");
    }
}
