<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class PaymentController extends Controller
{
    /**
     * Create a PayMongo checkout session
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
    

    /**
     * Create a PayMongo Payment Link
     */
    public function linkPay()
    {
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

    /**
     * Get Payment Link Status
     */
    public function linkStatus($linkid)
    {
        $response = Http::withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/links/{$linkid}");

        return response()->json($response->json());
    }

    /**
     * Process a Refund
     */
    public function refund(Request $request)
    {
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

    /**
     * Get Refund Status
     */
    public function refundStatus($id)
    {
        $response = Http::withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':')
        ])->get("https://api.paymongo.com/v1/refunds/{$id}");

        return response()->json($response->json());
    }
}
