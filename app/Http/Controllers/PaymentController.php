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
     * Create a PayMongo checkout session for a specific product
     */
    public function payForProduct(Request $request, $productId)
    {
        // Find the product
        $product = Product::findOrFail($productId);
        
        // Validate product availability
        if ($product->stocks <= 0 || $product->visibility !== 'Published' || $product->is_archived == 1) {
            return response()->json(['error' => 'Product is not available for purchase'], 400);
        }
        
        // Get quantity from request (default to 1)
        $quantity = $request->input('quantity', 1);
        
        // Validate quantity against available stock
        if ($quantity > $product->stocks) {
            return response()->json(['error' => 'Requested quantity exceeds available stock'], 400);
        }
        
        // Calculate amount in cents
        $amountInCents = $product->price * 100 * $quantity;
        
        $data = [
            'data' => [
                'attributes' => [
                    'line_items' => [
                        [
                            'currency'    => 'PHP',
                            'amount'      => $amountInCents,
                            'description' => $product->description ?? 'Product purchase',
                            'name'        => $product->product_name,
                            'quantity'    => $quantity,
                        ]
                    ],
                    'payment_method_types' => ['gcash'],
                    'success_url' => url('/payment/success/' . $productId),
                    'cancel_url'  => url('/payment/cancel'),
                    'description' => 'Purchase of ' . $product->product_name
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
    
        // Store payment session information
        Session::put('session_id', $responseData['data']['id']);
        Session::put('product_id', $productId);
        Session::put('quantity', $quantity);
        
        return response()->json([
            'checkout_url' => $responseData['data']['attributes']['checkout_url']
        ]);
    }

    /**
     * Handle successful payment
     */
    public function paymentSuccess($productId)
    {
        $sessionId = Session::get('session_id');
        $quantity = Session::get('quantity', 1);
        $product = Product::findOrFail($productId);
        
        // Verify payment completion with PayMongo
        $response = Http::withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");
        
        $responseData = $response->json();
        
        // Check if payment was successful
        if (isset($responseData['data']['attributes']['payment_intent']['status']) 
            && $responseData['data']['attributes']['payment_intent']['status'] === 'succeeded') {
            
            // Update product inventory
            $product->stocks = $product->stocks - $quantity;
            $product->save();
            
            // Clear session data
            Session::forget(['session_id', 'product_id', 'quantity']);
            
            return view('payment.success', ['product' => $product]);
        }
        
        return redirect('/')->with('error', 'Payment verification failed');
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