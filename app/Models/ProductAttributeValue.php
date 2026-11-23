<?php

namespace App\Models;

use App\Services\CatalogAttributeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProductAttributeValue extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Exactly one value column may be populated, matching the
        // attribute type (spec SS4.12).
        static::saving(function (ProductAttributeValue $value) {
            $populated = collect([
                'text' => $value->value_text,
                'number' => $value->value_number,
                'boolean' => $value->value_boolean,
            ])->filter(fn ($v) => $v !== null && $v !== '');
            if ($populated->count() > 1) {
                throw ValidationException::withMessages([
                    'value' => 'يجب تعبئة عمود قيمة واحد فقط حسب نوع الخاصية.',
                ]);
            }
            $attribute = $value->attribute;
            if ($attribute !== null && $populated->count() === 1) {
                $raw = match ($attribute->data_type) {
                    'number' => $value->value_number,
                    'boolean' => $value->value_boolean,
                    default => $value->value_text,
                };
                $error = app(CatalogAttributeService::class)->validateValue($attribute, $raw);
                if ($error !== null) {
                    throw ValidationException::withMessages([
                        'value' => 'قيمة غير صالحة لنوع الخاصية: '.$error,
                    ]);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['value_number' => 'decimal:3', 'value_boolean' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id');
    }

    /** Display value according to the attribute type. */
    public function displayValue(): string
    {
        return match ($this->attribute?->data_type) {
            'number' => rtrim(rtrim((string) $this->value_number, '0'), '.'),
            'boolean' => $this->value_boolean ? 'نعم' : 'لا',
            'select' => $this->value_label_snapshot ?? (string) $this->value_text,
            default => (string) $this->value_text,
        };
    }
}
