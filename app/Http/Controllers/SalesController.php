<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SalesController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            Log::error('Unauthorized: No user found with this token.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('Authenticated User:', ['id' => $user->id]);

        $sales = Sale::where('account_id', $user->id)->get();

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Verify payment status from PayMongo API
        $paymentId = $request->paymongo_payment_id;
        $paymongoResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode(env('PAYMONGO_SECRET_KEY') . ':'),
            'Content-Type' => 'application/json'
        ])->get("https://api.paymongo.com/v1/payments/$paymentId");

        $paymentData = $paymongoResponse->json();

        // Check if payment is successful
        if (isset($paymentData['data']['attributes']['status']) && $paymentData['data']['attributes']['status'] === 'paid') {
            $sale = Sale::create([
                'account_id' => $user->id,
                'product_id' => $request->product_id,
                'amount' => $request->amount,
                'paymongo_payment_id' => $paymentId,
                'created_at' => now(),
            ]);

            return response()->json(['message' => 'Sale recorded successfully', 'sale' => $sale], 201);
        }

        return response()->json(['error' => 'Payment not completed'], 400);
    }
}
