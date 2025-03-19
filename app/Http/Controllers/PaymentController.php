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
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
    
            $lineItems = [];
            $totalAmount = 0;
    
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
    
                // Validate product availability
                if ($product->stocks <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
                    return response()->json(['error' => "Product {$product->product_name} is not available for purchase"], 400);
                }
    
                // Validate quantity against available stock
                if ($item['quantity'] > $product->stocks) {
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
                        'payment_method_types' => ['gcash', 'paymaya'], // Add other payment methods if needed
                        'success_url' => url('/success'),
                        'cancel_url' => url('/cancel'),
                        'description' => 'Payment for multiple items',
                    ],
                ],
            ];
    
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
    
            DB::commit(); // Commit transaction if everything is successful
    
            return response()->json([
                'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            ]);
        } catch (Exception $e) {
            DB::rollBack(); // Rollback transaction in case of an exception
            Log::error("Payment processing error: " . $e->getMessage());
            return response()->json(['error' => 'Something went wrong. Please try again.'], 500);
        }
    }

    /**
     * Handle cancelled payment
     */
    public function paymentCancel()
    {
        // Clear session data
        Session::forget(['session_id', 'product_id', 'quantity']);

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

    /**
     * Handle PayMongo webhook
     */
    /**
 * Handle PayMongo webhook
 */
public function handlePaymongoWebhook(Request $request)
{
    Log::info('PayMongo Webhook Received:', $request->all());

    $data = $request->json()->get('data');
    $type = $request->json()->get('type');

    if ($type === 'checkout_session.payment_succeeded') {
        $checkoutSessionId = $data['id'];

        // Fetch the checkout session details from PayMongo
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['data']['attributes']['status']) && $responseData['data']['attributes']['status'] === 'paid') {
            $lineItems = $responseData['data']['attributes']['line_items'];

            DB::beginTransaction(); // Start transaction

            try {
                foreach ($lineItems as $lineItem) {
                    $productName = $lineItem['name'];
                    $quantity = $lineItem['quantity'];

                    // Find the product by name
                    $product = Product::where('product_name', $productName)->first();

                    if ($product) {
                        if ($product->stocks >= $quantity) {
                            // Deduct stock
                            $product->decrement('stocks', $quantity);

                            // Save order details
                            Order::create([
                                'user_id' => $responseData['data']['attributes']['metadata']['user_id'] ?? null, // Ensure this data is sent from frontend
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'total_price' => ($product->price * $quantity),
                                'payment_status' => 'paid',
                                'transaction_id' => $checkoutSessionId
                            ]);

                            Log::info("Order created successfully via webhook.", [
                                'product_id' => $product->id,
                                'product_name' => $product->product_name,
                                'quantity_ordered' => $quantity,
                                'remaining_stock' => $product->stocks
                            ]);
                        } else {
                            Log::error("Insufficient stock for product {$product->product_name} (via webhook). Requested: {$quantity}, Available: {$product->stocks}");
                            DB::rollBack();
                            return response('Insufficient stock', 400);
                        }
                    } else {
                        Log::error("Product not found: {$productName} (via webhook)");
                        DB::rollBack();
                        return response('Product not found', 404);
                    }
                }

                DB::commit(); // Commit transaction
                return response('Webhook handled successfully', 200);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error processing webhook: " . $e->getMessage());
                return response('Error processing webhook', 500);
            }
        } else {
            Log::warning('Checkout session not paid or status unknown (via webhook)', ['status' => $responseData['data']['attributes']['status'] ?? 'unknown']);
        }
    }

    return response('Webhook received', 200);
}

}