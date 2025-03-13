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
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    // Function to get a list of orders
    //shipment
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

            // Retrieve orders with 'processing' status
            $orders = Order::with('product') // Eager load the product relationship
                ->select('id', 'account_id', 'product_id', 'ship_to', 'quantity', 'total_amount', 'status', 'created_at', 'updated_at')
                ->where('account_id', $user->id)
                ->when($request->has('product_id'), function ($query) use ($request) {
                    $query->where('product_id', $request->product_id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('paginate', 10));

            // Transform the collection to update statuses and format the response
            $orders->getCollection()->transform(function ($order) {
                $now = Carbon::now();
                $orderCreatedAt = Carbon::parse($order->created_at);

                // Update statuses immediately within the same day
                if ($order->status === 'processing') {
                    $order->status = 'Toship';
                } elseif ($order->status === 'Toship') {
                    $order->status = 'shipping';
                } elseif ($order->status === 'shipping') {
                    $order->status = 'To Receive';
                } elseif ($order->status === 'To Receive') {
                    $order->status = 'Completed';
                }

                // Save the updated status
                $order->save();

                // Format the created_at and updated_at fields
                $order->created_at = $orderCreatedAt->format('F d Y');
                $order->updated_at = $now->format('F d Y');

                return [
                    'id' => $order->id,
                    'account_id' => $order->account_id,
                    'product_id' => $order->product_id,
                    'product_name' => $order->product ? $order->product->product_name : 'N/A',
                    'ship_to' => $order->ship_to,
                    'quantity' => $order->quantity,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
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

    public function updateOrderStatus(Request $request, $id)
{
    try {
        $user = Auth::user();
        Log::info('User updating order status:', ['user' => $user]);

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // ✅ Only Farmers (role_id = 2) can update order statuses
        if ($user->role_id !== 2) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Access denied. Only Farmers can update order statuses.',
            ], 403);
        }

        // Find the order
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        Log::info('Order status before update:', [
            'order_id' => $order->id,
            'current_status' => $order->status
        ]);

        // Define valid status transitions
        $statusFlow = [
            'Order placed' => 'Waiting for courier',
            'Waiting for courier' => 'In transit',
            'In transit' => 'Order delivered',
        ];

        // Check if the order can transition
        if (!isset($statusFlow[$order->status])) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Order status cannot be changed further.',
            ], 400);
        }

        // **CHECK FOR DELIVERY PROOF WHEN MARKING AS "ORDER DELIVERED"**
        if ($statusFlow[$order->status] === 'Order delivered') {
            if (!$request->hasFile('delivery_proof')) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Delivery proof image is required to mark the order as delivered.',
                ], 400);
            }

            // Validate the image file
            $request->validate([
                'delivery_proof' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
            ]);

            // Store the image
            $image = $request->file('delivery_proof');
            $directory = public_path('img/delivery_proofs');
            $fileName = 'DeliveryProof-' . $order->id . '-' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $image->move($directory, $fileName);
            $imagePath = asset('img/delivery_proofs/' . $fileName);

            // Save proof to the order
            $order->delivery_proof = $imagePath;
        }

        // Update the order status
        $order->status = $statusFlow[$order->status];
        $order->save();

        Log::info('Order status updated successfully:', [
            'order_id' => $order->id,
            'new_status' => $order->status
        ]);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Order status updated successfully.',
            'order' => [
                'id' => $order->id,
                'account_id' => $order->account_id,
                'product_id' => $order->product_id,
                'status' => $order->status,
                'ship_to' => $order->ship_to,
                'product_name' => $order->product ? $order->product->product_name : 'N/A',
                'delivery_proof' => $order->delivery_proof ?? null, // Include proof in response
                'created_at' => Carbon::now()->format('F d Y'),
                'updated_at' => Carbon::now()->format('F d Y'),
            ],
        ], 200);
    } catch (Throwable $e) {
        Log::error('Error updating order status:', ['error' => $e->getMessage()]);
        return response()->json([
            'isSuccess' => false,
            'message' => 'An error occurred while updating the order status.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


     //order received//
    public function confirmOrderReceived(Request $request, $id)
{
    try {
        // Authenticate user
        $user = Auth::user();
        Log::info('Customer confirming order received:', ['user' => $user]);

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

         // Check if user is a Farmer (2) or Consumer (3)
         if (!in_array($user->role_id, [2, 3])) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Only Farmers and Consumers can confirm order receipt.',
            ], 403);
        }

        // Find the order and check if it belongs to the consumer
        $order = Order::with('product')->where('account_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Order not found or does not belong to you.',
            ], 404);
        }

        // Ensure order is already "Order delivered" before marking it as "Order received"
        if ($order->status !== 'Order delivered') {
            return response()->json([
                'isSuccess' => false,
                'message' => 'You can only confirm receipt when the order is delivered.',
            ], 400);
        }

        // Update the order status to "Order received"
        $order->status = 'Order received';
        $order->save();

        Log::info('Order marked as received:', ['order_id' => $order->id, 'new_status' => $order->status]);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Order marked as received successfully.',
            'order' => [
                'id' => $order->id,
                'account_id' => $order->account_id,
                'product_id' => $order->product_id,
                'product_name' => $order->product ? $order->product->product_name : 'N/A',
                'status' => $order->status,
                'ship_to' => $order->ship_to,
                'updated_at' => Carbon::now()->format('F d Y'),
            ],
        ], 200);
    } catch (Throwable $e) {
        Log::error('Error confirming order received:', ['error' => $e->getMessage()]);
        return response()->json([
            'isSuccess' => false,
            'message' => 'An error occurred while updating the order status.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


public function getOrdersForPickup(Request $request)
{
    try {
        // ✅ Authenticate user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        // ✅ Ensure only Riders (role_id = 4) can access this function
        if ($user->role_id !== 4) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Access denied. Only Riders can retrieve pickup orders.',
            ], 403);
        }

        // ✅ Retrieve orders that are "Waiting for courier"
        $orders = Order::where('status', 'Waiting for courier')->get();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Orders for pickup retrieved successfully.',
            'orders' => $orders,
        ], 200);
    } catch (\Throwable $e) {
        Log::error('Error retrieving orders for pickup:', ['error' => $e->getMessage()]);
        return response()->json([
            'isSuccess' => false,
            'message' => 'An error occurred while retrieving orders.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


public function pickupOrder(Request $request, $id)
{
    try {
        // ✅ Authenticate user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        // ✅ Ensure only Riders (role_id = 4) can pick up orders
        if ($user->role_id !== 4) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Access denied. Only Riders can pick up orders.',
            ], 403);
        }

        // ✅ Find the order
        $order = Order::find($id);
        if (!$order) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        // ✅ Ensure order status is "Waiting for courier"
        if ($order->status !== 'Waiting for courier') {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Order cannot be picked up. It is not in "Waiting for courier" status.',
            ], 400);
        }

        // ✅ Update status to "In transit"
        $order->status = 'In transit';
        $order->save();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Order picked up successfully. Status updated to "In transit".',
            'order' => $order,
        ], 200);
    } catch (\Throwable $e) {
        Log::error('Error picking up order:', ['error' => $e->getMessage()]);
        return response()->json([
            'isSuccess' => false,
            'message' => 'An error occurred while picking up the order.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function uploadDeliveryProof(Request $request, $id)
{
    try {
        // Validate the uploaded image
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Ensure the user is authenticated
        if (!auth()->check()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Unauthorized. Please log in to upload proof of delivery.',
            ];

            // Log the API call
            $this->logAPICalls('uploadDeliveryProof', null, $request->all(), $response);

            return response()->json($response, 401);
        }

        // Check if the order exists
        $order = Order::find($id);
        if (!$order) {
            $response = [
                'isSuccess' => false,
                'message' => 'Order not found.',
            ];

            // Log the API call
            $this->logAPICalls('uploadDeliveryProof', null, $request->all(), $response);

            return response()->json($response, 404);
        }

        // Get authenticated user's account ID
        $accountId = auth()->id();

        // Handle the image upload
        $directory = public_path('delivery_proofs');
        $fileName = 'DeliveryProof-' . $accountId . '-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $request->file('image')->move($directory, $fileName);
        $filePath = asset('delivery_proofs/' . $fileName);

        // Update the order record
        $order->delivery_proofs = $filePath;
        $order->status = 'Order delivered';
        $order->save();

        $response = [
            'isSuccess' => true,
            'message' => 'Delivery proof uploaded successfully.',
            'delivery_proof' => $filePath,
        ];

        // Log the API call
        $this->logAPICalls('uploadDeliveryProof', $order->id, $request->all(), $response);

        return response()->json($response, 200);

    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to upload delivery proof.',
            'error' => $e->getMessage(),
        ];

        // Log the API call
        $this->logAPICalls('uploadDeliveryProof', null, $request->all(), $response);

        return response()->json($response, 500);
    }
}











    public function getCancelledOrders(Request $request)
    {
        try {
            // Authenticate the user
            $user = Auth::user();
            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not authenticated',
                ];
                $this->logAPICalls('getCancelledOrders', "", $request->all(), [$response]);
                return response()->json($response, 500);
            }

            // Check if the user's role_id is Farmer (role_id = 2)
            if ($user->role_id !== 2) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Access denied. Only Farmers can retrieve cancelled orders.',
                ];
                $this->logAPICalls('getCancelledOrders', $user->id, $request->all(), [$response]);
                return response()->json($response, 403);
            }

            // Retrieve orders with 'Cancelled' status
            $orders = Order::with('product') // Eager load the product relationship
                ->select('id', 'account_id', 'product_id', 'ship_to', 'quantity', 'total_amount', 'status', 'created_at', 'updated_at')
                ->where('account_id', $user->id)
                ->where('status', 'Cancelled') // Only get orders with 'Cancelled' status
                ->when($request->has('product_id'), function ($query) use ($request) {
                    $query->where('product_id', $request->product_id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('paginate', 10));

            // Transform the collection to format the response and include product_name
            $orders->getCollection()->transform(function ($order) {
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
                'message' => 'Cancelled orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while retrieving cancelled orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getRefundOrders(Request $request)
    {
        try {
            // Authenticate the user
            $user = Auth::user();
            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not authenticated',
                ];
                $this->logAPICalls('getRefundOrders', "", $request->all(), [$response]);
                return response()->json($response, 500);
            }

            // Check if the user's role_id is Farmer (role_id = 2)
            if ($user->role_id !== 2) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Access denied. Only Farmers can retrieve refund orders.',
                ];
                $this->logAPICalls('getRefundOrders', $user->id, $request->all(), [$response]);
                return response()->json($response, 403);
            }

            // Retrieve orders with 'Refund' status
            $orders = Order::with('product') // Eager load the product relationship
                ->select('id', 'account_id', 'product_id', 'ship_to', 'quantity', 'total_amount', 'status', 'created_at', 'updated_at')
                ->where('account_id', $user->id)
                ->where('status', 'Refund') // Only get orders with 'Refund' status
                ->when($request->has('product_id'), function ($query) use ($request) {
                    $query->where('product_id', $request->product_id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('paginate', 10));

            // Transform the collection to format the response and include product_name
            $orders->getCollection()->transform(function ($order) {
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
                'message' => 'Refund orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while retrieving refund orders.',
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
