<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CODOrderController extends Controller
{
    public function __construct()
    {
        // Ensure authentication for this controller
        $this->middleware('auth:sanctum'); // Adjust if using session-based auth
    }

    public function createCODOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            
        ]);
    
        if (!auth()->check()) {
            Log::error('User authentication failed.');
            return response()->json(['error' => 'User is not authenticated.'], 401);
        }
    
        $userId = auth()->id();
        Log::info('Authenticated user:', ['user_id' => $userId]);
    
        // Ensure that the account ID exists in the accounts table
        $accountExists = DB::table('accounts')->where('id', $userId)->exists();
        if (!$accountExists) {
            Log::error("Account ID {$userId} does not exist.");
            return response()->json(['error' => "Account ID {$userId} does not exist."], 400);
        }
    
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
    
                $order = Order::create([
                    'account_id' => $userId, // Ensure this user exists
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'total_amount' => $totalAmount,
                    'ship_to' => '',
                    'status' => 'Order placed',
                    'delivery_proof' => '', // Ensure a default value is set
                ]);
    
                $product->decrement('stocks', $item['quantity']);
    
                $orders[] = [
                    'order_id' => $order->id,
                    'product' => $product->product_name,
                    'quantity' => $item['quantity'],
                    'total_amount' => $totalAmount,
                    'status' => 'Order placed',
                ];
            }
    
            DB::commit();
    
            return response()->json([
                'message' => 'Order(s) placed successfully with Cash on Delivery.',
                'orders' => $orders
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order Placement Error:', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
}
