<?php

use App\Exports\ProductTemplateExport;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BulkImageAttachService;
use App\Services\ProductImportService;
use App\Services\QuantityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin user can access the panel, non-admin cannot, guest is redirected to login', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $customer = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($customer)->get('/admin')->assertForbidden();
    auth()->logout();
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('admin can create a meter product with fractional stock through the Filament form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = Category::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateProduct::class)
        ->fillForm([
            'name' => 'سلك نحاس 1.5 مم',
            'model_code' => 'CU-FORM-1',
            'slug' => 'cu-form-1',
            'category_id' => $category->id,
            'cost_price' => 50,
            'price' => 100,
            'stock_quantity' => '850.5',
            'track_stock' => true,
            'is_active' => true,
            'unit_code' => 'meter',
            'min_order_quantity' => '1',
            'quantity_step' => '0.25',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('model_code', 'CU-FORM-1')->first();
    expect($product)->not->toBeNull()
        ->and((string) $product->stock_quantity)->toBe('850.500')
        ->and($product->unit_code)->toBe('meter');
});

test('piece product fractional quantity is rejected by QuantityService rules used in the form', function () {
    $service = app(QuantityService::class);

    // The product form validates stock/min quantities through this exact
    // service call; a piece product (step 1) must reject fractions while
    // a meter product (step 0.25) accepts them.
    expect(fn () => $service->validate('1.5', '0', '1'))->toThrow(InvalidArgumentException::class);
    expect($service->validate('850.5', '0', '0.25'))->toBe('850.5');
});

test('category cannot exceed two levels', function () {
    $parent = Category::factory()->create(['parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => Category::factory()->create(['parent_id' => $child->id]))
        ->toThrow(ValidationException::class);
});

test('child category cannot redefine an inherited attribute key', function () {
    $parent = Category::factory()->create(['parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    CategoryAttribute::create([
        'category_id' => $parent->id, 'key' => 'poles', 'name_ar' => 'عدد الأقطاب',
        'data_type' => 'number', 'is_active' => true,
    ]);

    expect(fn () => CategoryAttribute::create([
        'category_id' => $child->id, 'key' => 'poles', 'name_ar' => 'أقطاب',
        'data_type' => 'number', 'is_active' => true,
    ]))->toThrow(ValidationException::class);
});

function importRow(array $overrides = []): array
{
    return array_merge([
        'سلك نحاس', 'CU-IMP-1', 'BrandX', 'تأسيس كهرباء', 'أسلاك وكابلات',
        '50', '100', null, '850.5', 'meter', '1', '0.25', null,
        'ملاحظات فنية: تجريبي', 'cross_section_mm2=1.5', 'وصف مختصر',
    ], $overrides);
}

function seedImportCatalog(): void
{
    $parent = Category::factory()->create(['name' => 'تأسيس كهرباء', 'parent_id' => null]);
    Category::factory()->create(['name' => 'أسلاك وكابلات', 'parent_id' => $parent->id]);
    CategoryAttribute::create([
        'category_id' => $parent->id, 'key' => 'cross_section_mm2', 'name_ar' => 'مساحة المقطع',
        'data_type' => 'number', 'unit_label' => 'mm²', 'is_filterable' => true, 'is_active' => true,
    ]);
}

test('importer creates products, auto-creates inactive brands, saves attributes and stock movements', function () {
    seedImportCatalog();
    $service = app(ProductImportService::class);

    $result = $service->processChunk([importRow()], 0);

    expect($result['created'])->toBe(1)->and($result['failed'])->toBe([]);
    $product = Product::where('model_code', 'CU-IMP-1')->first();
    expect((string) $product->stock_quantity)->toBe('850.500');
    expect(Brand::where('name', 'BrandX')->first()->is_active)->toBeFalse();
    expect($product->attributeValues()->first()->value_number)->toBe('1.500');
    expect(StockMovement::where('product_id', $product->id)->where('reason', 'import')->exists())->toBeTrue();
});

test('importer updates instead of duplicating on re-import (catalog identity)', function () {
    seedImportCatalog();
    $service = app(ProductImportService::class);
    $service->processChunk([importRow()], 0);

    $row = importRow();
    $row[6] = '120'; // new price
    $result = $service->processChunk([$row], 0);

    expect($result['updated'])->toBe(1)
        ->and(Product::where('model_code', 'CU-IMP-1')->count())->toBe(1)
        ->and((string) Product::where('model_code', 'CU-IMP-1')->first()->price)->toBe('120.00');
});

test('importer upserts with a null brand using catalog identity', function () {
    seedImportCatalog();
    $service = app(ProductImportService::class);
    $row = importRow();
    $row[2] = ''; // no brand

    $service->processChunk([$row], 0);
    $result = $service->processChunk([$row], 0);

    expect($result['updated'])->toBe(1)
        ->and(Product::where('model_code', 'CU-IMP-1')->count())->toBe(1)
        ->and(Product::where('model_code', 'CU-IMP-1')->first()->brand_id)->toBeNull();
});

test('importer rejects invalid unit, missing category, unknown attribute and fractional piece stock', function () {
    seedImportCatalog();
    $service = app(ProductImportService::class);

    $badUnit = importRow();
    $badUnit[9] = 'yard';
    $badCategory = importRow();
    $badCategory[1] = 'X-2';
    $badCategory[3] = 'تصنيف غير موجود';
    $badAttribute = importRow();
    $badAttribute[1] = 'X-3';
    $badAttribute[14] = 'unknown_key=5';
    $badStock = importRow();
    $badStock[1] = 'X-4';
    $badStock[8] = '1.5';
    $badStock[9] = 'piece';
    $badStock[11] = '1';

    $result = $service->processChunk([$badUnit, $badCategory, $badAttribute, $badStock], 0);

    expect($result['created'])->toBe(0)->and($result['failed'])->toHaveCount(4);
});

test('importer processes at most 200 rows per chunk', function () {
    $category = Category::factory()->create(['name' => 'تصنيف جماعي', 'parent_id' => null]);
    $service = app(ProductImportService::class);
    $rows = [];
    for ($i = 1; $i <= 201; $i++) {
        $rows[] = ["منتج {$i}", "BULK-{$i}", '', 'تصنيف جماعي', '', '10', '20', null, '5', 'piece', '1', '1', null, '', '', ''];
    }

    $first = $service->processChunk($rows, 0, 500); // limit is clamped to 200
    expect($first['created'])->toBe(200)->and(Product::count())->toBe(200);

    $second = $service->processChunk($rows, 200, 500);
    expect($second['created'])->toBe(1)->and(Product::count())->toBe(201);
});

test('template export uses the exact spec header', function () {
    expect((new ProductTemplateExport)->array())->toBe([ProductImportService::HEADERS]);
    expect(app(ProductImportService::class)->exportRows()[0])->toBe(ProductImportService::HEADERS);
});

test('bulk image attach matches model code pattern and reports unmatched files', function () {
    Product::factory()->create(['model_code' => 'IMG-1']);
    $service = app(BulkImageAttachService::class);

    $result = $service->attach([
        UploadedFile::fake()->image('IMG-1-2.jpg'),
        UploadedFile::fake()->image('IMG-1-1.jpg'),
        UploadedFile::fake()->image('NOPE-9-1.jpg'),
    ]);

    expect($result['attached'])->toBe(2)
        ->and($result['unmatched'])->toBe(['NOPE-9-1.jpg'])
        ->and(Product::where('model_code', 'IMG-1')->first()->getMedia('gallery'))->toHaveCount(2);
});
