<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('phone')->index();
            $table->string('phone_alt')->nullable();
            $table->string('email')->nullable();
            $table->string('governorate');
            $table->string('city');
            $table->text('address');
            $table->text('notes')->nullable();
            $table->string('payment_method');
            $table->string('payment_status')->default('pending')->index();
            $table->string('payment_proof')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('payment_expires_at')->nullable()->index();
            $table->timestamp('payment_verified_at')->nullable();
            $table->boolean('payment_reconciliation_required')->default(false);
            $table->string('status')->default('new')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->timestamp('coupon_released_at')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('stock_restoration_status')->default('not_required');
            $table->timestamp('stock_restored_at')->nullable();
            $table->text('stock_disposition_note')->nullable();
            $table->foreignId('stock_disposition_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sales_reversed_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_transaction_id')->nullable();
            $table->string('merchant_reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EGP');
            $table->string('status')->default('pending');
            $table->boolean('is_success')->default(false);
            $table->json('payload_redacted')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('refund_reference')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_note')->nullable();
            $table->string('reconciliation_status')->default('not_required');
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->string('unit_code');
            $table->string('unit_label_ar');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('quantity', 12, 3);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('change', 12, 3);
            $table->string('reason');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_code')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('orders');
    }
};
