<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Store settings (spec §4.1/§7, Phase 8). Every key is editable here
 * and saved to the `settings` table with cache invalidation, so the
 * storefront (which reads everything through `setting()`) updates on
 * the next render. Nothing business-related is hardcoded.
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'إعدادات المتجر';

    protected static ?string $title = 'إعدادات المتجر';

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** All editable setting keys (spec §4.1). */
    public const KEYS = [
        'store_name', 'logo', 'address', 'map_embed', 'email', 'tax_number', 'store_tax_note',
        'whatsapp_number', 'phones', 'social_links',
        'free_shipping_threshold', 'delivery_estimate_text', 'return_policy_days', 'low_stock_default',
        'payment_cod_enabled', 'payment_bank_enabled', 'payment_card_enabled', 'bank_transfer_details',
        'topbar_text', 'why_us_blocks',
        'motion_level',
        'ga4_measurement_id', 'search_console_verification', 'catalog_filter_max_options',
    ];

    public function mount(): void
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = setting($key, $this->defaultFor($key));
        }
        $this->form->fill($values);
    }

    private function defaultFor(string $key): mixed
    {
        return match ($key) {
            'free_shipping_threshold' => 3000,
            'return_policy_days' => 7,
            'low_stock_default' => 5,
            'payment_cod_enabled', 'payment_bank_enabled' => true,
            'payment_card_enabled' => false,
            'motion_level' => 'full',
            'catalog_filter_max_options' => 12,
            'phones', 'social_links' => [],
            'why_us_blocks' => [],
            default => '',
        };
    }

    public function form(Schema $schema): Schema
    {
        $iconOptions = collect(config('icons'))->mapWithKeys(fn ($label, $key) => [$key => $label.' ('.$key.')'])->all();

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')->tabs([
                    Tab::make('المتجر')->schema([
                        TextInput::make('store_name')->label('اسم المتجر'),
                        TextInput::make('logo')->label('رابط الشعار'),
                        Textarea::make('address')->label('العنوان'),
                        TextInput::make('email')->label('البريد الإلكتروني')->email(),
                        TextInput::make('tax_number')->label('الرقم الضريبي'),
                        Textarea::make('store_tax_note')->label('ملاحظة ضريبية/سعرية (اختيارية)')
                            ->helperText('نص يعرضه صاحب المتجر كما هو — لا توجد حسابات ضريبية تلقائية في هذا الإصدار.'),
                    ]),
                    Tab::make('التواصل')->schema([
                        TextInput::make('whatsapp_number')->label('رقم واتساب (بصيغة دولية، أرقام فقط)')
                            ->helperText('مثال: 201001234567 — اتركه فارغًا لإخفاء أزرار واتساب.'),
                        TagsInput::make('phones')->label('أرقام التليفونات المعروضة'),
                        Textarea::make('map_embed')->label('كود تضمين الخريطة'),
                        KeyValue::make('social_links')->label('روابط التواصل الاجتماعي')
                            ->keyLabel('المنصة')->valueLabel('الرابط'),
                    ]),
                    Tab::make('الشحن')->schema([
                        TextInput::make('free_shipping_threshold')->label('حد الشحن المجاني (ج.م)')->numeric()->minValue(0)
                            ->helperText('0 يعطّل الشحن المجاني.'),
                        TextInput::make('delivery_estimate_text')->label('نص مدة التوصيل الافتراضي'),
                        TextInput::make('return_policy_days')->label('أيام سياسة الاسترجاع')->numeric()->minValue(0),
                        TextInput::make('low_stock_default')->label('حد تنبيه المخزون المنخفض الافتراضي')->numeric()->minValue(0),
                    ]),
                    Tab::make('الدفع')->schema([
                        Toggle::make('payment_cod_enabled')->label('تفعيل الدفع عند الاستلام'),
                        Toggle::make('payment_bank_enabled')->label('تفعيل التحويل البنكي'),
                        Toggle::make('payment_card_enabled')->label('تفعيل الدفع بالبطاقة')
                            ->helperText('يبقى مخفيًا في المتجر حتى تكتمل إعدادات Paymob واختبار الكولباك (Phase 10).'),
                        Textarea::make('bank_transfer_details')->label('بيانات التحويل البنكي المعروضة عند إتمام الطلب'),
                    ]),
                    Tab::make('الصفحة الرئيسية')->schema([
                        TextInput::make('topbar_text')->label('نص الشريط العلوي'),
                        Repeater::make('why_us_blocks')->label('مميزات الشراء مننا')
                            ->schema([
                                Select::make('icon')->label('الأيقونة')->options($iconOptions)->required()
                                    ->helperText('أيقونات مسموحة فقط من القائمة المعتمدة.'),
                                TextInput::make('title')->label('العنوان')->required(),
                                Textarea::make('text')->label('النص'),
                            ])
                            ->columns(3)
                            ->defaultItems(0),
                    ]),
                    Tab::make('المظهر')->schema([
                        Select::make('motion_level')->label('مستوى الحركة الكهربائية')
                            ->options([
                                'full' => 'كاملة — كل الحركات والتأثيرات',
                                'lite' => 'خفيفة — ردود فعل أساسية فقط بدون حركات محيطة',
                                'off' => 'متوقفة — بدون أي حركة',
                            ])
                            ->required()
                            ->helperText('المتصفح يقدر يقلّل المستوى تلقائيًا (توفير بيانات / ضعف الجهاز / تقليل حركة النظام) لكنه لا يرفعه أبدًا.'),
                    ]),
                    Tab::make('التحليلات')->schema([
                        TextInput::make('ga4_measurement_id')->label('معرّف Google Analytics 4'),
                        TextInput::make('search_console_verification')->label('كود تحقق Google Search Console'),
                        TextInput::make('catalog_filter_max_options')->label('أقصى خيارات ظاهرة لكل فلتر تقني')->numeric()->minValue(1),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            // Normalize numeric settings so storefront comparisons stay decimal-safe.
            if (in_array($key, ['free_shipping_threshold', 'return_policy_days', 'low_stock_default', 'catalog_filter_max_options'], true)) {
                $value = is_numeric($value) ? $value + 0 : $value;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            Cache::forget("setting.{$key}");
        }
        Notification::make()->title('تم حفظ الإعدادات')->success()->send();
    }
}
