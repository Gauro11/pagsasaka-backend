<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Account;

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
    $account = Account::find($userId);

    if (!$account) {
        Log::error("Account ID {$userId} does not exist.");
        return response()->json(['error' => "Account ID {$userId} does not exist."], 400);
    }

    DB::beginTransaction();
    try {
        $orders = [];
        $fullName = trim("{$account->first_name} {$account->last_name}");
        $totalOverall = 0;

        foreach ($request->items as $item) {
            $product = Product::lockForUpdate()->find($item['product_id']);

            if (!$product) {
                throw new \Exception("Product ID {$item['product_id']} not found.");
            }

            if ($product->stocks < $item['quantity']) {
                throw new \Exception("Insufficient stock for {$product->product_name}. Available: {$product->stocks}, Requested: {$item['quantity']}");
            }

            $totalAmount = $product->price * $item['quantity'];
            $totalOverall += $totalAmount;

            $deliveryAddress = $account->delivery_address ?? 'No address provided';

            $order = Order::create([
                'account_id' => $userId,
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'total_amount' => $totalAmount,
                'ship_to' => $deliveryAddress,
                'status' => 'Order placed',
                'delivery_proof' => '',
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

        // ✅ Email body
        $emailBody = "Hello {$fullName},\n\n";
        $emailBody .= "Thank you for your Cash on Delivery order! Here's your receipt:\n\n";

        foreach ($orders as $item) {
            $emailBody .= "{$item['quantity']}x {$item['product']} - ₱" . number_format($item['total_amount'], 2) . "\n";
        }

        $emailBody .= "\nTotal Amount Due: ₱" . number_format($totalOverall, 2);
        $emailBody .= "\n\nPlease prepare the exact amount upon delivery.\n";
        $emailBody .= "If you have any questions, feel free to contact our support.\n\n";
        $emailBody .= "Best regards,\nYour Store Team";

        Mail::raw($emailBody, function ($message) use ($account, $fullName) {
            $message->to($account->email, $fullName)
                    ->subject('Your COD Order Receipt');
        });

        return response()->json([
            'message' => 'Order(s) placed successfully with Cash on Delivery. Email receipt sent.',
            'orders' => $orders
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Order Placement Error:', ['message' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 400);
    }
}
    
}

