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
