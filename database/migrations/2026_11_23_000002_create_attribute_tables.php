<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name_ar');
            $table->string('data_type');
            $table->string('unit_label')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category_id', 'key']);
            $table->index(['category_id', 'is_active', 'is_filterable']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 12, 3)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('value_label_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'category_attribute_id']);
            $table->index(['category_attribute_id', 'value_text']);
            $table->index(['category_attribute_id', 'value_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('category_attributes');
    }
};
