<?php

namespace App\Models;

use App\Enums\BannerPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => BannerPosition::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'sort_order' => 'integer'];
    }

    /**
     * Public URL for a stored banner image path: absolute URLs and
     * root-relative paths pass through; uploaded files resolve via
     * the public disk.
     */
    public static function imageUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
