<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Create a PayMongo checkout session for multiple products
     */
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

    // Process each item in the request
    foreach ($request->items as $item) {
        $productId = $item['product_id'];
        $quantity = $item['quantity'];
        
        // Find the product
        $product = Product::findOrFail($productId);
        
        // Validate product availability
        if ($product->stocks <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
            return response()->json(['error' => "Product {$product->product_name} is not available for purchase"], 400);
        }
        
        // Validate quantity against available stock
        if ($quantity > $product->stocks) {
            return response()->json(['error' => "Requested quantity exceeds available stock for {$product->product_name}"], 400);
        }
        
        // Calculate amount in cents for this item
        $amountInCents = $product->price * 100 * $quantity;
        
        // Track total amount
        $totalAmount += $amountInCents;
        
        // Store product ID and quantity for later use
        $productIds[] = $productId;
        $quantities[] = $quantity;
        $itemDescriptions[] = "{$quantity}x {$product->product_name}";
    }
    
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
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
    ])->post('https://api.paymongo.com/v1/links', $data);

    $responseData = $response->json();

    if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
        return response()->json(['error' => $responseData], 400);
    }
    
    // Return the payment link data
    return response()->json([
        'id' => $responseData['data']['id'],
        'checkout_url' => $responseData['data']['attributes']['checkout_url'],
        'status' => $responseData['data']['attributes']['status']
    ]);
}

/**
 * Check the status of a multiple items payment link and process if paid
 */
public function checkMultiPayLinkStatus($linkId)
{
    $response = Http::withHeaders([
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
    ])->get("https://api.paymongo.com/v1/links/{$linkId}");

    $responseData = $response->json();
    
    // Check if payment was successful
    if (isset($responseData['data']['attributes']['status']) 
        && $responseData['data']['attributes']['status'] === 'paid') {
        
        // Get product IDs and quantities from remarks
        $remarks = json_decode($responseData['data']['attributes']['remarks'], true);
        
        if (isset($remarks['product_ids']) && isset($remarks['quantities'])) {
            $productIds = $remarks['product_ids'];
            $quantities = $remarks['quantities'];
            
            // Update product inventory for each purchased product
            $purchasedProducts = [];
            
            for ($i = 0; $i < count($productIds); $i++) {
                $product = Product::findOrFail($productIds[$i]);
                $quantity = $quantities[$i];
                
                // Update stock (only if not already processed)
                $product->stocks = $product->stocks - $quantity;
                $product->save();
                
                $purchasedProducts[] = [
                    'product' => $product->product_name,
                    'quantity' => $quantity,
                    'price' => $product->price
                ];
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
        }
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
                            'currency'    => 'PHP',
                            'amount'      => 10000, // Amount in cents (100 PHP)
                            'description' => 'Test Payment',
                            'name'        => 'Test Product',
                            'quantity'    => 1,
                        ]
                    ],
                    'payment_method_types' => ['gcash'],
                    'success_url' => url('/success'),
                    'cancel_url'  => url('/cancel'),
                    'description' => 'Test Payment'
                ],
            ]
        ];
    
        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/checkout_sessions', $data);
    
        $responseData = $response->json();
    
        if ($response->failed() || !isset($responseData['data']['attributes']['checkout_url'])) {
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
                    'amount'      => 150050, // Amount in cents
                    'description' => 'Test transaction'
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/links', $data);

        return response()->json($response->json());
    }

    public function linkStatus($linkid)
    {
        // Existing method remains unchanged
        $response = Http::withHeaders([
            'Accept'        => 'application/json',
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
                    'amount'     => $request->amount * 100, // Convert PHP to cents
                    'payment_id' => $request->payment_id,
                    'reason'     => $request->reason
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->post('https://api.paymongo.com/v1/refunds', $data);

        return response()->json($response->json());
    }

    public function refundStatus($id)
    {
        // Existing method remains unchanged
        $response = Http::withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/refunds/{$id}");

        return response()->json($response->json());
    }
}