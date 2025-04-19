<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Product;
use App\Models\Order;
use App\Models\Cart; // Make sure this is at the top if not already
use Exception;

class PaymentController extends Controller
{
    
    public function payment(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authentication required. Please log in.',
            ], 401);
        }
    
        DB::beginTransaction();
    
        try {
            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }
    
            $account = Account::find(Auth::id());
            if (!$account) {
                DB::rollBack();
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Account not found for authenticated user.',
                ], 404);
            }
    
            $items = $request->input('items');
            $lineItems = [];
            $totalAmount = 0;
            $ordersData = [];
    
            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) {
                    DB::rollBack();
                    return response()->json([
                        'isSuccess' => false,
                        'message' => "Product ID {$item['product_id']} not found.",
                    ], 404);
                }
    
                $quantity = min((int)$item['quantity'], $product->stocks);
                if ($quantity <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
                    DB::rollBack();
                    return response()->json([
                        'isSuccess' => false,
                        'message' => "Product {$product->product_name} is not available for purchase.",
                    ], 400);
                }
    
                $subtotal = $product->price * $quantity;
                $totalAmount += $subtotal;
    
                $product->stocks -= $quantity;
                $product->save();
    
                $lineItems[] = [
                    'currency' => 'PHP',
                    'amount' => (int)($product->price * 100),
                    'description' => "{$quantity}x {$product->product_name}",
                    'name' => $product->product_name,
                    'quantity' => $quantity,
                ];
    
                $ordersData[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'total_amount' => number_format($subtotal, 2, '.', ''),
                ];
            }
    
            $fullName = trim("{$account->first_name} {$account->last_name}");
    
            $rawPhone = $account->phone_number;
            if (preg_match('/^09\d{9}$/', $rawPhone)) {
                $phone = '' . substr($rawPhone, 1);
            } elseif (preg_match('/^\d{10}$/', $rawPhone)) {
                $phone = $rawPhone;
            } else {
                $phone = null;
            }
    
            $data = [
                'data' => [
                    'attributes' => [
                        'line_items' => $lineItems,
                        'payment_method_types' => ['gcash', 'paymaya'],
                        'success_url' => url('https://pagsasaka.bpc-bsis4d.com/market'),
                        'cancel_url' => url('/cancel'),
                        
                        'metadata' => [
                            'account_id' => $account->id,
                            'items' => $ordersData,
                            'full_name' => $fullName,
                            'total_amount' => number_format($totalAmount, 2, '.', ''),
                        ],
                        'billing' => [
                            'name' => $fullName,
                            'email' => $account->email,
                            'phone' => $phone,
                        ],
                    ],
                ],
            ];
    
            Log::info("Sending PayMongo API Request", ['request' => $data]);
    
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':'),
            ])->post('https://api.paymongo.com/v1/checkout_sessions', $data);
    
            $responseData = $response->json();
    
            if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
                Log::error("PayMongo error", ['response' => $responseData]);
                DB::rollBack();
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Failed to create payment session.',
                    'paymongo_response' => $responseData,
                ], 400);
            }
    
            Session::put('checkout_session_id', $responseData['data']['id']);
    
            $deliveryAddress = $account->delivery_address ?? 'No address provided';
    
            foreach ($ordersData as $orderData) {
                Order::create([
                    'account_id' => $account->id,
                    'product_id' => $orderData['product_id'],
                    'rider_id' => null,
                    'ship_to' => $deliveryAddress,
                    'quantity' => $orderData['quantity'],
                    'total_amount' => $orderData['total_amount'],
                    'status' => 'Order placed',
                ]);
            }
    
            // ✅ Only delete cart items with status "CheckedOut"
            foreach ($ordersData as $orderData) {
                DB::table('carts')
                    ->where('account_id', $account->id)
                    ->where('product_id', $orderData['product_id'])
                    ->where('status', 'CheckedOut')
                    ->delete();
            }
    
            DB::commit();
    
            $emailBody = "Hello {$fullName},\n\n";
            $emailBody .= "Thank you for your purchase! Here's your receipt:\n\n";
    
            foreach ($ordersData as $item) {
                $product = Product::find($item['product_id']);
                $productName = $product ? $product->product_name : 'Unknown Product';
                $emailBody .= "{$item['quantity']}x {$productName} - ₱{$item['total_amount']}\n";
            }
    
            $emailBody .= "\nDelivery Address: {$deliveryAddress}";
            $emailBody .= "\nTotal Amount Paid: ₱" . number_format($totalAmount, 2, '.', '');
            $emailBody .= "\n\nIf you have any questions, feel free to contact our support.\n\nBest regards,\nYour Store Team";
    
            Mail::raw($emailBody, function ($message) use ($account, $fullName) {
                $message->to($account->email, $fullName)
                        ->subject('Your Payment Receipt');
            });
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Payment session created successfully.',
                'checkout_url' => $responseData['data']['attributes']['checkout_url'],
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'full_name' => $fullName,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Payment error", ['exception' => $e->getMessage()]);
            return response()->json([
                'isSuccess' => false,
                'message' => 'Something went wrong. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    

    

}
