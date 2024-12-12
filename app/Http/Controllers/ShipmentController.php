<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Shipment;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\ApiLog;
use Throwable;

class ShipmentController extends Controller
{
    // Function to get a list of orders
    public function getOrders(Request $request)
    {
        try {
            // Authenticate the user
            $user = Auth::user();
            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not authenticated',
                ];
                $this->logAPICalls('getOrders', "", $request->all(), [$response]);
                return response()->json($response, 500);
            }
    
            // Check if the user's role_id is Farmer (role_id = 2)
            if ($user->role_id !== 2) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Access denied. Only Farmers can retrieve orders.',
                ];
                $this->logAPICalls('getOrders', $user->id, $request->all(), [$response]);
                return response()->json($response, 403);
            }
    
            // Retrieve orders with eager loading for 'product'
            $orders = Order::with('product') // Eager load the product relationship
                ->select('id', 'account_id', 'product_id', 'ship_to', 'quantity', 'total_amount', 'status', 'created_at', 'updated_at')
                ->where('account_id', $user->id)
                ->when($request->has('product_id'), function ($query) use ($request) {
                    $query->where('product_id', $request->product_id); 
                })
                ->when($request->has('status'), function ($query) use ($request) {
                    $query->where('status', $request->status); 
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('paginate', 10));
    
            // Transform the collection to format the response and include product_name
            $now = Carbon::now();
            $orders->getCollection()->transform(function ($order) use ($now) {
                // Calculate elapsed time
                $elapsedTime = $now->diffInHours(Carbon::parse($order->created_at));
    
                // Update the status based on elapsed time
                if ($elapsedTime >= 96 && $order->status === 'To Receive') {
                    $order->status = 'Completed';
                } elseif ($elapsedTime >= 72 && $order->status === 'shipping') {
                    $order->status = 'To Receive';
                } elseif ($elapsedTime >= 48 && $order->status === 'Toship') {
                    $order->status = 'shipping';
                } elseif ($elapsedTime >= 24 && $order->status === 'processing') {
                    $order->status = 'Toship';
                }
    
                // Save status changes only if necessary
                if ($order->isDirty('status')) {
                    $order->save();
                }
    
                // Retrieve product_name from the related product model, or return 'N/A' if not found
                $productName = $order->product ? $order->product->product_name : 'N/A';
    
                // Format the created_at and updated_at fields to "December 11 2024"
                $order->created_at = Carbon::parse($order->created_at)->format('F d Y');
                $order->updated_at = Carbon::parse($order->updated_at)->format('F d Y');
    
                // Return the transformed order
                return [
                    'id' => $order->id,
                    'account_id' => $order->account_id,
                    'product_id' => $order->product_id,
                    'product_name' => $productName, // Assign the product name
                    'ship_to' => $order->ship_to,
                    'quantity' => $order->quantity,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => Carbon::parse($order->created_at)->format('F d Y'),
                    'updated_at' => Carbon::parse($order->updated_at)->format('F d Y'),
                ];
            });
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while retrieving orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    

    
    
    




    public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            // Handle logging error if necessary
            return false;
        }
        return true;
    }
}
