<?php

namespace App\Filament\Pages;

use App\Exports\ProductCatalogExport;
use App\Exports\ProductTemplateExport;
use App\Imports\ProductRowsImport;
use App\Services\BulkImageAttachService;
use App\Services\ProductImportService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Catalog Excel import/export + bulk image attach (spec 7.1).
 *
 * One HTTP request never processes a whole large file: the workbook is
 * stored after upload, then each "process next chunk" request handles
 * at most 200 rows (images: at most 20 files). Progress and tallies
 * live in the cache; failed rows are written to a downloadable CSV.
 */
class CatalogImport extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'استيراد وتصدير الكتالوج';

    protected static ?string $title = 'استيراد وتصدير الكتالوج';

    protected static string|UnitEnum|null $navigationGroup = 'الكتالوج';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.catalog-import';

    /** @var mixed Uploaded spreadsheet file. */
    public $importFile = null;

    /** @var array<int, mixed> Uploaded product images. */
    public array $imageFiles = [];

    public ?string $importToken = null;

    public int $totalRows = 0;

    public int $processedRows = 0;

    public int $createdCount = 0;

    public int $updatedCount = 0;

    /** @var array<int, array{row: int, model_code: string, reason: string}> */
    public array $failedRows = [];

    /** @var array<int, array{path: string, name: string}> */
    public array $pendingImages = [];

    /** @var array{attached: int, unmatched: array<int, string>, ambiguous: array<int, string>} */
    public array $imageResult = ['attached' => 0, 'unmatched' => [], 'ambiguous' => []];

    public function downloadTemplate(): StreamedResponse
    {
        return Excel::download(new ProductTemplateExport, 'products-template.xlsx');
    }

    public function downloadExport(): StreamedResponse
    {
        return Excel::download(new ProductCatalogExport, 'products-export.xlsx');
    }

    public function startImport(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $import = new ProductRowsImport;
        Excel::import($import, $this->importFile);
        $rows = $import->rows;

        if ($rows === []) {
            Notification::make()->title('الملف فارغ')->danger()->send();

            return;
        }

        $header = array_map(fn ($cell) => trim((string) $cell), array_values($rows[0]));
        if ($header !== ProductImportService::HEADERS) {
            Notification::make()
                ->title('أعمدة الملف لا تطابق القالب')
                ->body('حمّل القالب الرسمي واملأه كما هو دون تغيير أسماء الأعمدة أو ترتيبها.')
                ->danger()
                ->send();

            return;
        }

        $dataRows = array_slice($rows, 1);
        // Drop fully empty trailing rows.
        $dataRows = array_values(array_filter($dataRows, fn ($row) => collect($row)->filter(fn ($cell) => $cell !== null && trim((string) $cell) !== '')->isNotEmpty()));

        if (count($dataRows) > ProductImportService::MAX_ROWS_PER_FILE) {
            Notification::make()
                ->title('الملف أكبر من الحد الأقصى')
                ->body('الحد الأقصى 5,000 صف لكل ملف. قسّم الملف وحاول مرة أخرى.')
                ->danger()
                ->send();

            return;
        }

        $this->importToken = 'catalog-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        Cache::put("import.{$this->importToken}.rows", $dataRows, now()->addHours(4));
        $this->totalRows = count($dataRows);
        $this->processedRows = 0;
        $this->createdCount = 0;
        $this->updatedCount = 0;
        $this->failedRows = [];

        Notification::make()->title("تم تحميل {$this->totalRows} صفاً — اضغط «معالجة الدفعة التالية» للبدء")->success()->send();
    }

    public function processNextChunk(ProductImportService $service): void
    {
        if ($this->importToken === null || $this->processedRows >= $this->totalRows) {
            return;
        }
        $rows = Cache::get("import.{$this->importToken}.rows", []);
        $result = $service->processChunk($rows, $this->processedRows, ProductImportService::MAX_ROWS_PER_REQUEST);

        $chunkSize = min(ProductImportService::MAX_ROWS_PER_REQUEST, $this->totalRows - $this->processedRows);
        $this->processedRows += $chunkSize;
        $this->createdCount += $result['created'];
        $this->updatedCount += $result['updated'];
        $this->failedRows = array_merge($this->failedRows, $result['failed']);
        $this->writeFailedRowsCsv();

        if ($this->processedRows >= $this->totalRows) {
            Cache::forget("import.{$this->importToken}.rows");
            Notification::make()
                ->title('اكتمل الاستيراد')
                ->body("أُنشئ {$this->createdCount} · حُدّث {$this->updatedCount} · فشل ".count($this->failedRows))
                ->success()
                ->send();
        }
    }

    public function downloadFailedRows(): ?StreamedResponse
    {
        $path = "imports/failed-{$this->importToken}.csv";
        if ($this->importToken === null || ! Storage::exists($path)) {
            Notification::make()->title('لا توجد صفوف فاشلة للتحميل')->warning()->send();

            return null;
        }

        return Storage::download($path);
    }

    private function writeFailedRowsCsv(): void
    {
        if ($this->importToken === null) {
            return;
        }
        $lines = ['row,model_code,reason'];
        foreach ($this->failedRows as $failed) {
            $lines[] = implode(',', [
                $failed['row'],
                '"'.str_replace('"', '""', $failed['model_code']).'"',
                '"'.str_replace('"', '""', $failed['reason']).'"',
            ]);
        }
        Storage::put("imports/failed-{$this->importToken}.csv", implode("\n", $lines));
    }

    public function startImageAttach(): void
    {
        $this->validate([
            'imageFiles' => ['required', 'array', 'max:200'],
            'imageFiles.*' => ['image', 'max:4096'],
        ]);

        $this->pendingImages = [];
        foreach ($this->imageFiles as $file) {
            $stored = $file->store('imports/images');
            $this->pendingImages[] = [
                'path' => Storage::path($stored),
                'name' => $file->getClientOriginalName(),
                'stored' => $stored,
            ];
        }
        $this->imageResult = ['attached' => 0, 'unmatched' => [], 'ambiguous' => []];

        Notification::make()->title('تم رفع '.count($this->pendingImages).' صورة — اضغط «معالجة الدفعة التالية» للربط')->success()->send();
    }

    public function processNextImages(BulkImageAttachService $service): void
    {
        if ($this->pendingImages === []) {
            return;
        }
        $chunk = array_slice($this->pendingImages, 0, BulkImageAttachService::MAX_FILES_PER_REQUEST);
        $result = $service->attachPaths($chunk, BulkImageAttachService::MAX_FILES_PER_REQUEST);

        $this->imageResult['attached'] += $result['attached'];
        $this->imageResult['unmatched'] = array_merge($this->imageResult['unmatched'], $result['unmatched']);
        $this->imageResult['ambiguous'] = array_merge($this->imageResult['ambiguous'], $result['ambiguous']);

        foreach ($chunk as $item) {
            if (isset($item['stored'])) {
                Storage::delete($item['stored']);
            }
        }
        $this->pendingImages = array_slice($this->pendingImages, BulkImageAttachService::MAX_FILES_PER_REQUEST);
    }

    public function getProgressPercentProperty(): int
    {
        return $this->totalRows > 0 ? (int) floor($this->processedRows * 100 / $this->totalRows) : 0;
    }
}
