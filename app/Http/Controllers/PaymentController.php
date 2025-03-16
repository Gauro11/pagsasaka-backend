<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // Import Log facade
use Illuminate\Support\Facades\DB;



class PaymentController extends Controller
{
    /**
     * Create a PayMongo payment link for multiple products
     */
    public function createMultipleItemsPayLink(Request $request)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        'items' => 'required|array',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $totalAmount = 0;
    $productIds = [];
    $quantities = [];
    $itemDescriptions = [];
    $purchasedProducts = [];

    DB::beginTransaction(); // Start database transaction

    try {
        // Process each item in the request
        foreach ($request->items as $item) {
            $productId = $item['product_id'];
            $quantity = (int) $item['quantity'];

            // Find the product
            $product = Product::findOrFail($productId);

            // Validate product availability
            if ($product->stocks <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
                DB::rollBack();
                return response()->json(['error' => "Product {$product->product_name} is not available for purchase"], 400);
            }

            // Validate quantity against available stock
            if ($quantity > $product->stocks) {
                DB::rollBack();
                return response()->json(['error' => "Requested quantity exceeds available stock for {$product->product_name}"], 400);
            }

            // Deduct stock
            $product->decrement('stocks', $quantity);

            // Store transaction details
            $purchasedProducts[] = [
                'product' => $product->product_name,
                'quantity' => $quantity,
                'price' => $product->price
            ];

            Log::info("Stock deducted successfully.", [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'quantity_deducted' => $quantity,
                'remaining_stock' => $product->stocks
            ]);

            // Calculate amount in cents
            $amountInCents = $product->price * 100 * $quantity;
            $totalAmount += $amountInCents;

            // Store product ID and quantity for later use
            $productIds[] = $productId;
            $quantities[] = $quantity;
            $itemDescriptions[] = "{$quantity}x {$product->product_name}";
        }

        DB::commit(); // Commit transaction if everything is successful

        // Create the payment link data
        $data = [
            'data' => [
                'attributes' => [
                    'amount' => $totalAmount,
                    'description' => 'Purchase of: ' . implode(', ', $itemDescriptions),
                    'remarks' => json_encode([
                        'product_ids' => $productIds,
                        'quantities' => $quantities
                    ])
                ]
            ]
        ];

        // Send the request to PayMongo
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/links', $data);

        $responseData = $response->json();

        if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
            DB::rollBack();
            return response()->json(['error' => $responseData], 400);
        }

        // Return the payment link data
        return response()->json([
            'id' => $responseData['data']['id'],
            'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            'status' => $responseData['data']['attributes']['status']
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Error processing payment: " . $e->getMessage());
        return response()->json(['error' => 'An error occurred while processing the payment.'], 500);
    }
}


    /**
     * Check the status of a multiple items payment link and process if paid
     */
    public function checkMultiPayLinkStatus($linkId)
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/links/{$linkId}");

        $responseData = $response->json();

        // Log the entire response for debugging
        Log::info('PayMongo API Response:', ['response' => $responseData]);

        // Check if payment was successful
        if (isset($responseData['data']['attributes']['status']) && $responseData['data']['attributes']['status'] === 'paid') {

            // Get product IDs and quantities from remarks
            $remarks = json_decode($responseData['data']['attributes']['remarks'], true);

            if (isset($remarks['product_ids']) && isset($remarks['quantities'])) {
                $productIds = $remarks['product_ids'];
                $quantities = $remarks['quantities'];

                // Update product inventory for each purchased product
                $purchasedProducts = [];

                for ($i = 0; $i < count($productIds); $i++) {
                    $product = Product::findOrFail($productIds[$i]);
                    $quantity = (int) $quantities[$i];  //Cast to integer. Ensure $quantity is treated as a number.

                    // Check if enough stock is available
                    if ($product->stocks >= $quantity) {
                        // Deduct stock using decrement (atomic operation)
                        $product->decrement('stocks', $quantity);

                        $purchasedProducts[] = [
                            'product' => $product->product_name,
                            'quantity' => $quantity,
                            'price' => $product->price
                        ];

                         Log::info("Stock deducted successfully.", [
                            'product_id' => $product->id,
                            'product_name' => $product->product_name,
                            'quantity_deducted' => $quantity,
                            'remaining_stock' => $product->stocks
                        ]);
                    } else {
                        // Log an error if not enough stock
                        Log::error("Insufficient stock for product {$product->product_name} (ID: {$product->id}). Requested: {$quantity}, Available: {$product->stocks}");
                        return response()->json(['error' => "Insufficient stock for product {$product->product_name}."], 400);
                    }
                }

                return response()->json([
                    'status' => 'paid',
                    'purchased_items' => $purchasedProducts,
                    'payment_details' => [
                        'amount' => $responseData['data']['attributes']['amount'] / 100, // Convert back to PHP from cents
                        'payment_id' => $responseData['data']['id'],
                        'paid_at' => $responseData['data']['attributes']['payments'][0]['attributes']['paid_at'] ?? null
                    ]
                ]);
            } else {
                Log::error("Product IDs or Quantities missing from remarks.", ['remarks' => $remarks]);
                return response()->json(['error' => 'Product IDs or quantities missing from remarks.'], 500); //Internal server error because this is unexpected
            }
        } else {
            Log::warning("Payment not yet paid or status unknown.", ['status' => $responseData['data']['attributes']['status'] ?? 'unknown']);
        }

        // Return the current status
        return response()->json([
            'status' => $responseData['data']['attributes']['status'] ?? 'unknown',
            'link_details' => $responseData['data']
        ]);
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
     * The original pay method - keep for backward compatibility
     */
    public function pay()
    {
        $data = [
            'data' => [
                'attributes' => [
                    'line_items' => [
                        [
                            'currency' => 'PHP',
                            'amount' => 10000, // Amount in cents (100 PHP)
                            'description' => 'Test Product', // Description for the line item
                            'name' => 'Test Product',
                            'quantity' => 1,
                        ]
                    ],
                    'payment_method_types' => ['gcash'],
                    'success_url' => url('/success'),
                    'cancel_url' => url('/cancel'),
                    'description' => 'Payment for Test Product', // Overall description
                ],
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/checkout_sessions', $data);

        $responseData = $response->json();

        if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
            Log::error("PayMongo API Error:", ['error' => $responseData, 'request_data' => $data]);
            return response()->json(['error' => $responseData], 400);
        }

        Session::put('session_id', $responseData['data']['id']);
        return response()->json([
            'checkout_url' => $responseData['data']['attributes']['checkout_url']
        ]);
    }

    // Keep your existing methods below
    public function linkPay()
    {
        // Existing method remains unchanged
        $data = [
            'data' => [
                'attributes' => [
                    'amount' => 150050, // Amount in cents
                    'description' => 'Test transaction'
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/links', $data);

        return response()->json($response->json());
    }

    public function linkStatus($linkid)
    {
        // Existing method remains unchanged
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/links/{$linkid}");

        return response()->json($response->json());
    }

    public function refund(Request $request)
    {
        // Existing method remains unchanged
        $data = [
            'data' => [
                'attributes' => [
                    'amount' => $request->amount * 100, // Convert PHP to cents
                    'payment_id' => $request->payment_id,
                    'reason' => $request->reason
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/refunds', $data);

        return response()->json($response->json());
    }

    public function refundStatus($id)
    {
        // Existing method remains unchanged
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/refunds/{$id}");

        return response()->json($response->json());
    }

    /**
     * Handle PayMongo webhook
     */
       /**
     * Handle PayMongo webhook
     */
    public function handlePaymongoWebhook(Request $request)
    {
        Log::info('PayMongo Webhook Received:', $request->all()); // Log the entire payload

        $data = $request->json()->get('data');
        $type = $request->json()->get('type');

        if ($type === 'checkout_session.payment_succeeded') {
            $checkoutSessionId = $data['id'];

            //  Fetch the checkout session details from PayMongo to confirm
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
            ])->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['data']['attributes']['status']) && $responseData['data']['attributes']['status'] === 'paid') {
                // Extract line items and deduct stock
                $lineItems = $responseData['data']['attributes']['line_items'];

                foreach ($lineItems as $lineItem) {
                    $productName = $lineItem['name'];
                    $quantity = $lineItem['quantity'];

                    // Find the product by name (This is not ideal, use product_id if you can pass it to paymongo)
                     $product = Product::where('product_name', $productName)->first();

                    if ($product) {
                        if ($product->stocks >= $quantity) {
                            $product->decrement('stocks', $quantity);
                            Log::info("Stock deducted successfully via webhook.", [
                                'product_id' => $product->id,
                                'product_name' => $product->product_name,
                                'quantity_deducted' => $quantity,
                                'remaining_stock' => $product->stocks
                            ]);
                        } else {
                            Log::error("Insufficient stock for product {$product->product_name} (ID: {$product->id}) via webhook.  Requested: {$quantity}, Available: {$product->stocks}");
                        }
                    } else {
                        Log::error("Product not found: {$productName} via webhook.");
                    }
                }
            } else {
                Log::error("Checkout session not paid or invalid status via webhook.", ['checkoutSessionId' => $checkoutSessionId, 'response' => $responseData]);
            }
        }

        return response()->json(['status' => 'success']); //  Always return a 200 OK
    }

}
