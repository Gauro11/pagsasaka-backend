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
            // Check if the user is authenticated
            $user = Auth::user();
            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not authenticated',
                ];
                // Log the failed API call
                $this->logAPICalls('getOrders', "", $request->all(), [$response]);
                return response()->json($response, 500);
            }

            // Check if the user's role_id is Farmer (role_id = 2)
            if ($user->role_id !== 2) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Access denied. Only Farmers can retrieve orders.',
                ];
                // Log the failed API call
                $this->logAPICalls('getOrders', $user->id, $request->all(), [$response]);
                return response()->json($response, 403);
            }

            // Retrieve orders filtered by the logged-in user's account_id
            $orders = Order::select('id', 'account_id', 'product_id', 'ship_to', 'quantity', 'total_amount', 'status', 'created_at', 'updated_at')
                ->where('account_id', $user->id) // Filter by the user's account_id
                ->when($request->has('product_id'), function ($query) use ($request) {
                    $query->where('product_id', $request->product_id); // Filter by product_id if provided
                })
                ->when($request->has('status'), function ($query) use ($request) {
                    $query->where('status', $request->status); // Filter by status if provided
                })
                ->orderBy('created_at', 'desc') // Sort by latest orders first
                ->paginate($request->get('paginate', 10)); // Default to 10 items per page

            // Count unique products for the logged-in user
            $uniqueProductCount = Order::where('account_id', $user->id)
                ->distinct('product_id')
                ->count('product_id');

            // Valid ENUM values for status
            $validStatuses = ['processing', 'Toship', 'shipping', 'To Receive', 'Completed', 'Cancelled'];

            // Transform the collection to format dates and process status updates
            $now = Carbon::now();
            $orders->getCollection()->transform(function ($order) use ($now, $validStatuses) {
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

                // Save status changes only if valid and necessary
                if (in_array($order->status, $validStatuses) && $order->isDirty('status')) {
                    $order->save();
                }

                // Format the created_at and updated_at fields to "December 11 2024"
                $order->created_at = Carbon::parse($order->created_at)->format('F d Y');
                $order->updated_at = Carbon::parse($order->updated_at)->format('F d Y');

                // Return the transformed order
                return $order;
            });

            // Format the response
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'account_id' => $order->account_id,
                    'product_id' => $order->product_id,
                    'ship_to' => $order->ship_to, //shiip to the location 
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
                'uniqueProductCount' => $uniqueProductCount, // Include the count of unique products
                'orders' => $formattedOrders,
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
