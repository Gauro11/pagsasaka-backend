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
use App\Models\Cart;
use App\Models\Payout;
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
                    'payment_method' => 'E-Wallet',
                ]);
            }

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

    // Fetch payment history for the seller
    public function getPaymentHistory(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authentication required. Please log in.',
            ], 401);
        }

        $farmerId = Auth::id();

        $orders = Order::whereHas('product', function ($query) use ($farmerId) {
                $query->where('account_id', $farmerId);
            })
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('payment_method', 'COD')
                      ->where('status', 'Order Delivered');
                })->orWhere('payment_method', 'E-Wallet');
            })
            ->with(['product'])
            ->get()
            ->map(function ($order) {
                return [
                  
                    'date' => $order->created_at->format('m/d/Y'),
                    'product_name' => $order->product->product_name ?? 'Unknown Product',
                    'payment_method' => $order->payment_method,
                    'amount' => $order->payment_method === 'E-Wallet' ? '0.00' : number_format($order->total_amount, 2, '.', ''),
                    'buyer_account_id' => $order->account_id,
                ];
            });

        return response()->json([
            'isSuccess' => true,
            'transactions' => $orders,
        ]);
    }

    // Check if seller is eligible for payout and get total sales
    public function checkPayoutEligibility(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authentication required. Please log in.',
            ], 401);
        }

        $accountId = Auth::id();
        $orders = Order::whereHas('product', function ($query) use ($accountId) {
            $query->where('account_id', $accountId);
        })
        ->where(function ($query) {
            $query->where(function ($q) {
                $q->where('payment_method', 'COD')
                  ->where('status', 'Order Delivered');
            })->orWhere('payment_method', 'E-Wallet');
        })
        ->where('status', '!=', 'Paid Out')
        ->get();

        $totalSales = $orders->sum('total_amount');
        $eligible = $totalSales >= 500;

        return response()->json([
            'isSuccess' => true,
            'eligible' => $eligible,
            'totalSales' => number_format($totalSales, 2, '.', ''),
        ]);
    }

    // Get available slots for a given date or date range
    public function getAvailableSlots(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|string', // Can be a single date or range (e.g., '2025-05-05' or '2025-05-01/2025-05-31')
        ]);

        if ($validator->fails()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $dateInput = $request->input('date');
        $maxSlotsPerTimeSlot = 2; // Maximum bookings per time slot
        $timeSlots = [
            '10:00-11:00',
            '11:00-12:00',
            '12:00-13:00',
            '13:00-14:00',
            '14:00-15:00',
            '15:00-16:00',
            '16:00-17:00',
        ];

        $today = now()->startOfDay();
        $availableSlots = [];

        if (strpos($dateInput, '/') !== false) {
            // Handle date range (for calendar)
            [$startDate, $endDate] = explode('/', $dateInput);
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            if ($start->lt($today)) {
                $start = $today;
            }

            if ($end->lt($start)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'End date cannot be before start date.',
                ], 400);
            }

            $currentDate = $start->copy();
            while ($currentDate->lte($end)) {
                foreach ($timeSlots as $timeSlot) {
                    $existingPayouts = Payout::where('scheduled_date', $currentDate->format('Y-m-d'))
                        ->where('time_slot', $timeSlot)
                        ->count();

                    $availableCount = max(0, $maxSlotsPerTimeSlot - $existingPayouts);
                    if ($availableCount > 0) {
                        $availableSlots[] = [
                            'date' => $currentDate->format('Y-m-d'),
                            'time_slot' => $timeSlot,
                            'is_available' => true,
                            'available_slots' => $availableCount,
                        ];
                    }
                }
                $currentDate->addDay();
            }
        } else {
            // Handle single date (for time slot selection)
            $date = \Carbon\Carbon::parse($dateInput);
            if ($date->lt($today)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Selected date cannot be in the past.',
                ], 400);
            }

            foreach ($timeSlots as $timeSlot) {
                $existingPayouts = Payout::where('scheduled_date', $date->format('Y-m-d'))
                    ->where('time_slot', $timeSlot)
                    ->count();

                $availableCount = max(0, $maxSlotsPerTimeSlot - $existingPayouts);
                $availableSlots[] = [
                    'date' => $date->format('Y-m-d'),
                    'time_slot' => $timeSlot,
                    'is_available' => $availableCount > 0,
                    'available_slots' => $availableCount > 0 ? $availableCount : null,
                ];
            }
        }

        return response()->json([
            'isSuccess' => true,
            'available_slots' => $availableSlots,
        ]);
    }

    // Request a payout and schedule it
    public function requestPayout(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authentication required. Please log in.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'time_slot' => 'required|string|in:10:00-11:00,11:00-12:00,12:00-13:00,13:00-14:00,14:00-15:00,15:00-16:00,16:00-17:00',
            'total_sales' => 'required|numeric|min:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $accountId = Auth::id();
        $scheduledDate = $request->input('date');
        $timeSlot = $request->input('time_slot');
        $totalSales = $request->input('total_sales');

        $maxSlotsPerTimeSlot = 2;

        // Check existing payouts for the selected date and time slot
        $existingPayouts = Payout::where('scheduled_date', $scheduledDate)
            ->where('time_slot', $timeSlot)
            ->count();

        if ($existingPayouts >= $maxSlotsPerTimeSlot) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No available slots for the selected date and time.',
            ], 400);
        }

        // Calculate queue number for the day
        $queueNumber = Payout::where('scheduled_date', $scheduledDate)
            ->count() + 1;

        // Create the payout request
        $payout = Payout::create([
            'account_id' => $accountId,
            'amount' => $totalSales,
            'scheduled_date' => $scheduledDate,
            'time_slot' => $timeSlot,
            'queue_number' => $queueNumber,
            'status' => 'Pending',
        ]);

        // Mark orders as paid out
        Order::whereHas('product', function ($query) use ($accountId) {
            $query->where('account_id', $accountId);
        })
        ->where(function ($query) {
            $query->where(function ($q) {
                $q->where('payment_method', 'COD')
                  ->where('status', 'Order Delivered');
            })->orWhere('payment_method', 'E-Wallet');
        })
        ->where('status', '!=', 'Paid Out')
        ->update(['status' => 'Paid Out']);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Payout requested successfully.',
            'payout' => [
                'scheduled_date' => $payout->scheduled_date,
                'time_slot' => $payout->time_slot,
                'queue_number' => $payout->queue_number,
                'amount' => number_format($payout->amount, 2, '.', ''),
            ],
        ]);
    }

     // Export payment history to CSV
     public function exportPaymentHistoryToCSV(Request $request)
     {
         if (!Auth::check()) {
             return response()->json([
                 'isSuccess' => false,
                 'message' => 'Authentication required. Please log in.',
             ], 401);
         }
 
         $farmerId = Auth::id();
 
         // Get orders where the product belongs to the logged-in farmer (same logic as getPaymentHistory)
         $orders = Order::whereHas('product', function ($query) use ($farmerId) {
             $query->where('account_id', $farmerId);
         })
         ->with(['product'])
         ->get()
         ->map(function ($order) {
             return [
                 'date' => $order->created_at->format('m/d/Y'),
                 'product_name' => $order->product ? $order->product->product_name : 'Unknown Product',
                 'payment_method' => $order->payment_method ?? 'Unknown',
                 'amount' => $order->payment_method === 'E-Wallet' ? '0.00' : number_format($order->total_amount, 2, '.', ''),
             ];
         });

        if ($orders->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No payment history found.',
            ], 404);
        }

        $csvFileName = 'payment_history_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$csvFileName\"",
        ];

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Date', 'Product Name', 'Payment Method', 'Amount']);

        foreach ($orders as $order) {
            fputcsv($output, [
                $order['date'],
                $order['product_name'],
                $order['payment_method'],
                $order['amount'],
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return response($csvContent, 200, $headers);
    }
}