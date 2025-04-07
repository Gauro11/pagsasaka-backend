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

class PaymentController extends Controller
{
    /**
     * Process payment for multiple items
     */
    public function pay(Request $request)
    {
        DB::beginTransaction(); // Start transaction
    
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'items' => 'required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'buyer_address' => 'required|string|max:255', // Added for your Order model
            ]);
    
            if ($validator->fails()) {
                Log::warning("Validation failed in pay method", ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }
    
            $lineItems = [];
            $totalAmount = 0;
    
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
    
                // Validate product availability
                if ($product->stocks <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
                    Log::warning("Product not available", ['product_id' => $item['product_id'], 'product_name' => $product->product_name]);
                    return response()->json(['error' => "Product {$product->product_name} is not available for purchase"], 400);
                }
    
                // Validate quantity against available stock
                if ($item['quantity'] > $product->stocks) {
                    Log::warning("Insufficient stock", ['product_id' => $item['product_id'], 'requested_quantity' => $item['quantity'], 'available_stock' => $product->stocks]);
                    return response()->json(['error' => "Requested quantity exceeds available stock for {$product->product_name}"], 400);
                }
    
                // Deduct stock (Consider reserving instead of deducting here)
                $product->stocks -= $item['quantity'];
                $product->save();
    
                // Prepare line items for PayMongo
                $lineItems[] = [
                    'currency' => 'PHP',
                    'amount' => $product->price * 100, // Convert PHP to cents
                    'description' => "{$item['quantity']}x {$product->product_name}",
                    'name' => $product->product_name,
                    'quantity' => $item['quantity'],
                ];
    
                // Calculate total amount
                $totalAmount += $product->price * 100 * $item['quantity'];
            }
    
            // Create checkout session data
            $data = [
                'data' => [
                    'attributes' => [
                        'line_items' => $lineItems,
                        'payment_method_types' => ['gcash', 'paymaya'],
                        'success_url' => url('/payment/verify'), // Ensure this URL is correct
                        'cancel_url' => url('/cancel'),
                        'description' => 'Payment for multiple items',
                        'metadata' => [ // Add metadata for order creation
                            'account_id' => auth()->id() ?? null,
                            'items' => $request->items,
                            'buyer_address' => $request->buyer_address,
                        ],
                    ],
                ],
            ];
    
            Log::info("Sending request to PayMongo", ['data' => $data]);
    
            // Send request to PayMongo API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':'),
            ])->post('https://api.paymongo.com/v1/checkout_sessions', $data);
    
            $responseData = $response->json();
    
            if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
                Log::error("PayMongo API Error:", ['error' => $responseData, 'request_data' => $data]);
                DB::rollBack(); // Rollback transaction if API call fails
                return response()->json(['error' => $responseData], 400);
            }
    
            // Store session ID in session for verification
            Session::put('checkout_session_id', $responseData['data']['id']);
            Log::info("Checkout session created", [
                'session_id' => $responseData['data']['id'],
                'checkout_url' => $responseData['data']['attributes']['checkout_url']
            ]);
    
            DB::commit(); // Commit transaction if everything is successful
    
            return response()->json([
                'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            ]);
        } catch (Exception $e) {
            DB::rollBack(); // Rollback transaction in case of an exception
            Log::error("Payment processing error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Something went wrong. Please try again.'], 500);
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
            $buyer_address = $responseData['data']['attributes']['metadata']['buyer_address'] ?? '';
    
            Log::info("Creating orders", [
                'items' => $items,
                'account_id' => $accountId,
                'buyer_address' => $buyer_address
            ]);
    
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Since stock was already deducted in pay(), just create the order
                $order = Order::create([
                    'account_id' => $accountId,
                    'rider_id' => null, // As per your schema, initially null
                    'product_id' => $product->id,
                    'buyer_address' => $buyer_address,
                    'quantity' => $item['quantity'],
                    'total_amount' => $product->price * $item['quantity'],
                    'status' => 'Order placed pending', // Match your database status
                    'delivery_proof' => null, // Initially null
                ]);
    
                Log::info("Order created successfully", [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'buyer_address' => $buyer_address,
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