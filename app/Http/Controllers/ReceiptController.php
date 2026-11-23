<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bank-transfer receipt handling (spec §4.8, Phase 10).
 *
 * Upload: the order owner (authenticated) or a guest who proves the
 * same order-number + phone match used by order tracking. The file
 * goes to the PRIVATE local disk — it is never publicly addressable.
 * A receipt is evidence for the admin's manual verification only; it
 * never changes payment status by itself.
 *
 * View: admins only, streamed through this authorized route.
 */
class ReceiptController extends Controller
{
    public function upload(Request $request, string $orderNumber): RedirectResponse
    {
        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();

        abort_unless($order->payment_method === PaymentMethod::BankTransfer, 422);
        abort_unless($this->canAccess($request, $order), 403);

        $request->validate([
            'receipt' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $file = $request->file('receipt');
        $path = 'receipts/'.$order->order_number.'/'.Str::uuid().'.'.$file->getClientOriginalExtension();
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        if ($order->payment_proof !== null) {
            Storage::disk('local')->delete($order->payment_proof);
        }
        $order->payment_proof = $path;
        $order->save();

        return back()->with('receipt_uploaded', true);
    }

    /** Admin-only view of a private receipt. */
    public function show(Request $request, Order $order): StreamedResponse
    {
        abort_unless($request->user() !== null && $request->user()->is_admin, 403);
        abort_if($order->payment_proof === null, 404);
        abort_unless(Storage::disk('local')->exists($order->payment_proof), 404);

        return Storage::disk('local')->download($order->payment_proof);
    }

    private function canAccess(Request $request, Order $order): bool
    {
        $user = $request->user();
        if ($user !== null && ($user->is_admin || ($order->user_id !== null && (int) $order->user_id === (int) $user->id))) {
            return true;
        }

        // Guest: the success-page session, or an exact phone match with
        // the order (the same proof as guest order tracking, §4.9).
        if (session()->get('order.success.'.$order->order_number) === true) {
            return true;
        }
        $phone = (string) $request->input('phone', '');

        return $phone !== '' && Phone::normalize($phone) === $order->phone;
    }
}
