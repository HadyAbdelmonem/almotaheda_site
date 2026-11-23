<?php

namespace App\Exports;

use App\Services\ProductImportService;
use Maatwebsite\Excel\Concerns\FromArray;

/** Full catalog export using the same columns as the import template. */
class ProductCatalogExport implements FromArray
{
    public function array(): array
    {
        return app(ProductImportService::class)->exportRows();
    }
}
