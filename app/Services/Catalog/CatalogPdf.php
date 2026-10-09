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
