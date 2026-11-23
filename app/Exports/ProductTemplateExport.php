<?php

namespace App\Exports;

use App\Services\ProductImportService;
use Maatwebsite\Excel\Concerns\FromArray;

/** Template download: the exact spec 7.1 header row only. */
class ProductTemplateExport implements FromArray
{
    public function array(): array
    {
        return [ProductImportService::HEADERS];
    }
}
