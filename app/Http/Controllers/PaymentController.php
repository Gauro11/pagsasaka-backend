<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Cache;
use App\Models\Account;
class PaymentController extends Controller
{
    /**
     * Process payment for multiple items
     */
    public function payment(Request $request, $account_id, $product_id)
{
    DB::beginTransaction();

    try {
        // 🔍 Find account by ID instead of using auth()
        $account = Account::find($account_id);
        $product = Product::find($product_id);

        if (!$account || !$product) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid account or product.',
            ], 404);
        }

        // ✅ Validate quantity from cache or default to 1
        $quantity = Cache::get('purchase_' . $account->id . '_' . $product->id, 1);
        $quantity = min($quantity, $product->stocks);  // Ensure quantity doesn't exceed available stock

        if ($quantity <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
            return response()->json([
                'isSuccess' => false,
                'message' => "Product is not available for purchase.",
            ], 400);
        }

        // 🧮 Calculate total price
        $subtotal = $product->price * $quantity;
        $totalAmount = number_format($subtotal, 2, '.', '');  // Format to 2 decimal places

        // 📦 Reduce stock
        $product->stocks -= $quantity;
        $product->save();

        // 📄 Build line items for PayMongo
        $lineItems = [
            [
                'currency' => 'PHP',
                'amount' => $product->price * 100, // Convert PHP to cents (1 PHP = 100 cents)
                'description' => "{$quantity}x {$product->product_name}",
                'name' => $product->product_name,
                'quantity' => (int) $quantity,  // Ensure quantity is an integer
            ]
        ];

        // 📍 Use account's delivery address
        $shipTo = $account->delivery_address ?? 'No address provided';

        // 🔤 Get full name of the buyer (concatenate first name and last name)
        $fullName = trim("{$account->first_name} {$account->last_name}");

        // 🚀 Prepare checkout session data
        $data = [
            'data' => [
                'attributes' => [
                    'line_items' => $lineItems,
                    'payment_method_types' => ['gcash', 'paymaya'], // You can add more payment methods if necessary
                    'success_url' => url('/payment/verify'),
                    'cancel_url' => url('/cancel'),
                    'description' => 'Payment for product',
                    'metadata' => [
                        'account_id' => $account->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'ship_to' => $shipTo,
                        'full_name' => $fullName,  // Include full name in metadata
                        'total_amount' => $totalAmount,  // Include total amount
                    ],
                ],
            ],
        ];

        // Log the request for debugging purposes
        Log::info("Sending request to PayMongo API", ['data' => $data]);

        // Send request to PayMongo API
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':'),
        ])->post('https://api.paymongo.com/v1/checkout_sessions', $data);

        $responseData = $response->json();

        if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
            // Rollback transaction if API call fails
            Log::error("PayMongo API Error:", ['error' => $responseData, 'request_data' => $data]);
            DB::rollBack();
            return response()->json(['error' => 'Payment session creation failed. Please try again.'], 400);
        }

        // Store session ID in session for verification
        Session::put('checkout_session_id', $responseData['data']['id']);

        // Now create the Order record
        $order = Order::create([
            'account_id' => $account->id,
            'product_id' => $product->id,
            'rider_id' => null, // If you're assigning a rider later, you can update this field
            'ship_to' => $shipTo,
            'quantity' => $quantity,
            'total_amount' => $totalAmount,
            'status' => 'Order placed', // Set the status of the order
            // You can set additional fields like cancellation_reason, refund_reason, delivery_proof later if needed
        ]);

        // Commit transaction
        DB::commit();

        // Return checkout URL to the frontend for redirection
        return response()->json([
            'isSuccess' => true,
            'message' => 'Payment session created successfully.',
            'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            'total_amount' => $totalAmount,  // Include total amount in the response
            'full_name' => $fullName,  // Include full name in the response
            'ship_to' => $shipTo,  // Include delivery address in the response
        ]);
    } catch (Exception $e) {
        // Rollback transaction in case of an exception
        DB::rollBack();
        Log::error("Payment processing error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Something went wrong. Please try again later.'], 500);
    }
}

    



    /**
     * Verify payment and save to orders table
     */
    public function verifyPayment(Request $request)
    {
        Log::info("verifyPayment method called");
    
        DB::beginTransaction();
        
        try {
            $sessionId = Session::get('checkout_session_id');
            if (!$sessionId) {
                Log::error("Checkout session ID not found in session");
                return redirect('/')->with('error', 'Payment session expired');
            }
    
            Log::info("Verifying payment for session", ['session_id' => $sessionId]);
    
            // Verify payment status
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
            ])->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");
    
            $responseData = $response->json();
    
            if ($response->failed() || !isset($responseData['data']['attributes']['status'])) {
                Log::error('Payment verification failed', ['response' => $responseData]);
                DB::rollBack();
                return redirect('/')->with('error', 'Payment verification failed');
            }
    
            Log::info("Payment status", ['status' => $responseData['data']['attributes']['status']]);
    
            if ($responseData['data']['attributes']['status'] !== 'paid') {
                Log::warning("Payment not completed", ['status' => $responseData['data']['attributes']['status']]);
                DB::rollBack();
                return redirect('/')->with('error', 'Payment not completed');
            }
    
            // Payment is successful, create orders
            $items = $responseData['data']['attributes']['metadata']['items'] ?? [];
            $accountId = $responseData['data']['attributes']['metadata']['account_id'] ?? null;
            $shipTo = $responseData['data']['attributes']['metadata']['ship_to'] ?? '';
    
            Log::info("Creating orders", [
                'items' => $items,
                'account_id' => $accountId,
                'ship_to' => $shipTo
            ]);
    
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Since stock was already deducted in pay(), just create the order
                $order = Order::create([
                    'account_id' => $accountId,
                    'rider_id' => null, // As per your schema, initially null
                    'product_id' => $product->id,
                    'ship_to' => $shipTo,
                    'quantity' => $item['quantity'],
                    'total_amount' => $product->price * $item['quantity'],
                    'status' => 'Order placed pending', // Match your database status
                    'delivery_proof' => null, // Initially null
                ]);
    
                Log::info("Order created successfully", [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'ship_to' => $shipTo,
                ]);
            }
    
            DB::commit();
            Session::forget('checkout_session_id');
            Log::info("Payment verified and orders saved successfully");
            return redirect('/')->with('message', 'Payment successful and order placed');
    
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Payment verification error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect('/')->with('error', 'Error processing payment');
        }
    }

    /**
     * Handle cancelled payment
     */
    public function paymentCancel()
    {
        // Clear session data
        Session::forget(['session_id', 'product_id', 'quantity', 'checkout_session_id']);
        Log::info("Payment cancelled, session cleared");
        return redirect('/')->with('message', 'Payment cancelled');
    }

    /**
     * Process a refund for a payment
     */
    public function refund(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'payment_id' => 'required|string',
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            Log::warning("Validation failed in refund method", ['errors' => $validator->errors()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'data' => [
                'attributes' => [
                    'amount' => $request->amount * 100, // Convert PHP to cents
                    'payment_id' => $request->payment_id,
                    'reason' => $request->reason
                ]
            ]
        ];

        Log::info('Initiating refund', [
            'payment_id' => $request->payment_id,
            'amount' => $request->amount,
            'reason' => $request->reason
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/refunds', $data);

        $responseData = $response->json();

        if ($response->failed()) {
            Log::error('Refund failed', ['response' => $responseData]);
            return response()->json(['error' => $responseData], 400);
        }

        Log::info('Refund initiated successfully', ['refund_id' => $responseData['data']['id'] ?? 'unknown']);
        return response()->json($responseData);
    }

    /**
     * Check the status of a refund
     */
    public function refundStatus($id)
    {
        Log::info('Checking refund status', ['refund_id' => $id]);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/refunds/{$id}");

        $responseData = $response->json();

        if ($response->failed()) {
            Log::error('Error fetching refund status', ['response' => $responseData]);
            return response()->json(['error' => $responseData], 400);
        }

        return response()->json($responseData);
    }
}