<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable commercial invoice/receipt (spec §7.2). Admin only.
 * All values come from immutable order snapshots — never live
 * product data. This is NOT an Egyptian Tax Authority e-invoice.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        abort_unless($request->user()?->is_admin, 403);

        $order->load(['items', 'user']);

        return view('orders.invoice', ['order' => $order]);
    }
}
