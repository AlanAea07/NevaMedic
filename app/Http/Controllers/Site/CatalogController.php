<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Services\Catalog\CatalogPdf;

class CatalogController extends Controller
{
    public function download(Sector $sector, CatalogPdf $catalog)
    {
        return $this->document($sector, $catalog, 'attachment');
    }

    public function preview(Sector $sector, CatalogPdf $catalog)
    {
        return $this->document($sector, $catalog, 'inline');
    }

    private function document(Sector $sector, CatalogPdf $catalog, string $disposition)
    {
        return response($catalog->render($sector))->withHeaders([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="neva-medic-'.$sector->slug.'-'.now()->year.'.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
