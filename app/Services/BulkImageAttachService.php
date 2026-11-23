<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;

/**
 * Bulk image attach (spec 7.1): files named <model_code>-<n>.<ext>
 * attach to the matching product's gallery in n order. At most 20
 * files are processed per call; unmatched and ambiguous files are
 * reported, never silently dropped or overwritten.
 */
class BulkImageAttachService
{
    public const MAX_FILES_PER_REQUEST = 20;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array{attached: int, unmatched: array<int, string>, ambiguous: array<int, string>}
     */
    public function attach(array $files, int $limit = self::MAX_FILES_PER_REQUEST): array
    {
        $items = array_map(fn (UploadedFile $file) => [
            'path' => $file->getRealPath(),
            'name' => $file->getClientOriginalName(),
        ], array_values($files));

        return $this->attachPaths($items, $limit);
    }

    /**
     * Same behavior for already-stored files (the admin page stores
     * uploads first, then processes them in chunks of 20 per request).
     *
     * @param  array<int, array{path: string, name: string}>  $items
     * @return array{attached: int, unmatched: array<int, string>, ambiguous: array<int, string>}
     */
    public function attachPaths(array $items, int $limit = self::MAX_FILES_PER_REQUEST): array
    {
        $limit = max(1, min($limit, self::MAX_FILES_PER_REQUEST));
        $items = array_slice(array_values($items), 0, $limit);

        // Process in <n> order so gallery order follows the file names.
        usort($items, fn ($a, $b) => $this->parse($a['name'])[1] <=> $this->parse($b['name'])[1]);

        $result = ['attached' => 0, 'unmatched' => [], 'ambiguous' => []];
        foreach ($items as $item) {
            [$modelCode] = $this->parse($item['name']);
            if ($modelCode === null) {
                $result['unmatched'][] = $item['name'];

                continue;
            }
            $matches = Product::query()->where('model_code', $modelCode)->get();
            if ($matches->count() === 0) {
                $result['unmatched'][] = $item['name'];

                continue;
            }
            if ($matches->count() > 1) {
                $result['ambiguous'][] = $item['name'];

                continue;
            }
            $matches->first()->addMedia($item['path'])
                ->usingFileName($item['name'])
                ->toMediaCollection('gallery');
            $result['attached']++;
        }

        return $result;
    }

    /** @return array{0: ?string, 1: int} [model_code, n] */
    private function parse(string $filename): array
    {
        if (preg_match('/^(.+)-(\d+)\.(jpe?g|png|webp|gif)$/i', $filename, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [null, PHP_INT_MAX];
    }
}
