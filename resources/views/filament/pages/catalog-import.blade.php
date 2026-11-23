<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">قالب وتصدير الكتالوج</x-slot>
            <div class="flex flex-wrap gap-3">
                <x-filament::button wire:click="downloadTemplate" icon="heroicon-o-arrow-down-tray">
                    تحميل القالب
                </x-filament::button>
                <x-filament::button wire:click="downloadExport" color="gray" icon="heroicon-o-arrow-up-tray">
                    تصدير الكتالوج الحالي
                </x-filament::button>
            </div>
            <p class="mt-3 text-sm text-gray-500">
                الأعمدة بالترتيب: {{ implode('، ', \App\Services\ProductImportService::HEADERS) }}.
                قيم unit_code المسموحة: {{ implode('، ', array_keys(config('units'))) }}.
                التحديث يعتمد على كود الموديل + الماركة (لا تُنشأ نسخ مكررة).
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">استيراد منتجات (حتى 200 صف في كل طلب، وبحد أقصى 5,000 صف)</x-slot>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium">ملف Excel / CSV</label>
                    <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="mt-1 block w-full text-sm">
                    @error('importFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <x-filament::button wire:click="startImport" wire:loading.attr="disabled">
                    رفع الملف وبدء الاستيراد
                </x-filament::button>

                @if($importToken)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span>التقدم: {{ $processedRows }} / {{ $totalRows }} صف</span>
                            <span>{{ $this->progressPercent }}%</span>
                        </div>
                        <div class="mt-1 h-3 w-full overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full bg-primary-500" style="inline-size: {{ $this->progressPercent }}%"></div>
                        </div>
                        <p class="mt-2 text-sm">أُنشئ: {{ $createdCount }} · حُدّث: {{ $updatedCount }} · فشل: {{ count($failedRows) }}</p>
                        @if($processedRows < $totalRows)
                            <x-filament::button wire:click="processNextChunk" wire:loading.attr="disabled" class="mt-2">
                                معالجة الدفعة التالية (200 صف)
                            </x-filament::button>
                        @endif
                        @if(count($failedRows))
                            <div class="mt-3">
                                <x-filament::button wire:click="downloadFailedRows" color="danger" icon="heroicon-o-arrow-down-tray">
                                    تحميل تقرير الصفوف الفاشلة
                                </x-filament::button>
                                <ul class="mt-2 list-inside list-disc text-sm text-danger-600">
                                    @foreach(array_slice($failedRows, 0, 10) as $failed)
                                        <li>صف {{ $failed['row'] }} ({{ $failed['model_code'] }}): {{ $failed['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">ربط الصور بالجملة (حتى 20 صورة في كل طلب)</x-slot>
            <div class="space-y-4">
                <p class="text-sm text-gray-500">اسم الملف يجب أن يكون بصيغة &lt;كود الموديل&gt;-&lt;الترتيب&gt; مثل CU-1.5-PVC-1.jpg — تُربط الصور بمعرض المنتج بنفس الترتيب ولا تُستبدل الصور الحالية.</p>
                <div>
                    <input type="file" wire:model="imageFiles" multiple accept="image/*" class="block w-full text-sm">
                    @error('imageFiles') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    @error('imageFiles.*') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <x-filament::button wire:click="startImageAttach" wire:loading.attr="disabled">رفع الصور</x-filament::button>

                @if(count($pendingImages) || $imageResult['attached'])
                    <p class="text-sm">رُبطت: {{ $imageResult['attached'] }} · متبقية: {{ count($pendingImages) }}</p>
                    @if(count($pendingImages))
                        <x-filament::button wire:click="processNextImages" wire:loading.attr="disabled">معالجة الدفعة التالية (20 صورة)</x-filament::button>
                    @endif
                    @if($imageResult['unmatched'])
                        <p class="text-sm text-danger-600">ملفات لم تُطابق أي منتج: {{ implode('، ', $imageResult['unmatched']) }}</p>
                    @endif
                    @if($imageResult['ambiguous'])
                        <p class="text-sm text-danger-600">ملفات غامضة (أكثر من منتج بنفس الكود): {{ implode('، ', $imageResult['ambiguous']) }}</p>
                    @endif
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
