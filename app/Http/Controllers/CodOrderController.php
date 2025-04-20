<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Account;
use App\Models\Cart;

class CODOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
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

        Log::info("Account {$userId} has been authenticated. Proceeding with order creation.");

        DB::beginTransaction();
        try {
            $orders = [];
            $fullName = trim("{$account->first_name} {$account->last_name}");
            $totalOverall = 0;

            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if (!$product) {
                    Log::warning("Product ID {$item['product_id']} not found.");
                    throw new \Exception("Product ID {$item['product_id']} not found.");
                }

                if ($product->stocks < $item['quantity']) {
                    Log::warning("Insufficient stock for Product ID {$item['product_id']}: Requested {$item['quantity']}, Available {$product->stocks}");
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
                    'payment_method' => 'COD', // Add payment method
                ]);

                Log::info("Order ID {$order->id} created for Product ID {$product->id} by Account ID {$userId}");

                $product->decrement('stocks', $item['quantity']);

                Log::info("Product ID {$product->id} stock decremented by {$item['quantity']}.");

                $deleted = Cart::where('account_id', $userId)
                    ->where('product_id', $item['product_id'])
                    ->where('status', 'CheckedOut')
                    ->delete();

                if ($deleted) {
                    Log::info("Cart item with Product ID {$item['product_id']} and Account ID {$userId} has been deleted from the cart.");
                } else {
                    Log::warning("Cart item with Product ID {$item['product_id']} and Account ID {$userId} not found for deletion.");
                }

                $orders[] = [
                    'order_id' => $order->id,
                    'product' => $product->product_name,
                    'quantity' => $item['quantity'],
                    'total_amount' => $totalAmount,
                    'status' => 'Order placed',
                ];
            }

            DB::commit();

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

            Log::info("Email sent to {$account->email} with the order details.");

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