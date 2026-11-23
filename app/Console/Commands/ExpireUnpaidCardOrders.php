<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expire unpaid CARD orders older than 60 minutes (spec §4.8).
 * Cancellation goes through OrderService so stock is restored exactly
 * once and the coupon is released once. Bank-transfer orders NEVER
 * auto-expire. If a successful callback arrives later it is recorded
 * by the callback handler and flagged for reconciliation — this
 * command and the callback take the same order lock, so they cannot
 * double-restore stock.
 */
class ExpireUnpaidCardOrders extends Command
{
    protected $signature = 'payments:expire-card-orders';

    protected $description = 'Cancel unpaid card orders older than 60 minutes and expire their payment attempts';

    public function handle(OrderService $orders): int
    {
        $candidates = Order::query()
            ->where('payment_method', PaymentMethod::Card)
            ->where('payment_status', PaymentStatus::Pending)
            ->whereIn('status', [OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Processing])
            ->where('placed_at', '<=', now()->subMinutes(60))
            ->pluck('id');

        $expired = 0;
        foreach ($candidates as $id) {
            $didExpire = DB::transaction(function () use ($id, $orders) {
                $order = Order::query()->lockForUpdate()->find($id);
                if ($order === null
                    || $order->payment_method !== PaymentMethod::Card
                    || $order->payment_status !== PaymentStatus::Pending
                    || $order->paymentTransactions()->where('status', PaymentTransactionStatus::Paid)->exists()) {
                    return false; // A successful callback won the race.
                }

                $orders->changeStatus($order, OrderStatus::Cancelled, null, 'card_payment_expired');

                $order->paymentTransactions()
                    ->where('status', PaymentTransactionStatus::Pending)
                    ->update(['status' => PaymentTransactionStatus::Expired]);
                $order->payment_status = PaymentStatus::Failed;
                $order->save();

                return true;
            });

            if ($didExpire) {
                $expired++;
                Log::channel('payments')->info('unpaid card order expired and cancelled', ['order_id' => $id]);
            }
        }

        $this->info("Expired {$expired} unpaid card order(s).");

        return self::SUCCESS;
    }
}
