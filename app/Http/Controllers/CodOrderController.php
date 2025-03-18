<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CODOrder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CODOrderController extends Controller
{
    public function createCODOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'buyer_address' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $orders = [];

            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Product ID {$item['product_id']} not found.");
                }

                if ($product->stocks < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->product_name}. Available: {$product->stocks}, Requested: {$item['quantity']}");
                }

                $totalAmount = $product->price * $item['quantity'];

                $order = CODOrder::create([
                    'account_id' => auth()->check() ? auth()->id() : null,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'total_amount' => $totalAmount,
                    'ship_to' => $request->buyer_address,
                    'status' => 'Pending',
                ]);

                // Deduct stock
                $product->decrement('stocks', $item['quantity']);

                $orders[] = [
                    'order_id' => $order->id,
                    'product' => $product->product_name,
                    'quantity' => $item['quantity'],
                    'total_amount' => $totalAmount,
                    'status' => 'Pending'
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Order(s) placed successfully with Cash on Delivery.',
                'orders' => $orders
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
