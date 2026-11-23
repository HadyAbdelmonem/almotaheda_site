<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Reads an uploaded catalog workbook into raw rows. Processing the
 * rows is chunked by ProductImportService (max 200 per request,
 * spec 7.1); this class only loads the sheet into memory after the
 * 5,000-row cap has been checked by the caller.
 */
class ProductRowsImport implements ToArray
{
    /** @var array<int, array<int, mixed>> */
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }
}
