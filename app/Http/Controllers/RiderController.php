<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Rider;
use App\Models\Order;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Throwable;

class RiderController extends Controller
{
    // Get rider profile with total delivered amount//

    public function getRiderEarningsSummary($id)
{
    try {
        // Find the rider based on the provided ID
        $rider = Rider::find($id);

        // Ensure the rider exists and is a rider (role_id = 4)
        if (!$rider || $rider->role_id != 4) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Rider not found or not authorized.',
            ], 404);
        }

        // Get total COD amount of delivered orders for this rider
        $totalCodDelivered = Order::where('rider_id', $rider->id)
            ->where('status', 'Order delivered') // Match ENUM exactly
            ->where('payment_method', 'COD')
            ->sum('total_amount');

        // E-wallet amount is always zero
        $totalEWalletDelivered = 0;

        return response()->json([
            'isSuccess' => true,
            'message' => 'Rider delivery earnings retrieved successfully.',
            'earnings' => [
                'cod' => '₱' . number_format($totalCodDelivered, 2),
                'ewallet' => '₱' . number_format($totalEWalletDelivered, 2),
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve delivery earnings.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function approveRiderEarnings($id)
{
    try {
        // Find the rider
        $rider = Rider::find($id);

        // Validate that the rider exists and is of role_id 4
        if (!$rider || $rider->role_id != 4) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Rider not found or not authorized.',
            ], 404);
        }

        // Get all delivered COD orders for the rider
        $orders = Order::where('rider_id', $rider->id)
            ->where('status', 'Order delivered')
            ->where('payment_method', 'COD')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No COD delivered orders found for this rider.',
            ], 404);
        }

        // Update each order: reset rider_id to null
        foreach ($orders as $order) {
            $order->rider_id = null;
            $order->save();
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Rider earnings approved and rider ID cleared from delivered orders.',
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to approve rider earnings.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function cancelOrderByRider(Request $request, $orderId)
{
    try {
        // Assume the rider is authenticated
        $riderId = auth()->id(); // or use token-based logic

        // Find the order and validate it belongs to this rider
        $order = Order::where('id', $orderId)
            ->where('rider_id', $riderId)
            ->first();

        if (!$order) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Order not found or does not belong to the rider.',
            ], 404);
        }

        // Update status to Cancelled
        $order->status = 'Cancelled';
        $order->save();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Order successfully cancelled by rider.',
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to cancel order.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function getApprovedRiders()
{
    try {
        // Fetch all riders with status 'Approve'
        $riders = Rider::where('status', 'Approve')
            ->select('id', 'first_name', 'last_name', 'email', 'phone_number')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'data' => $riders,
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to fetch approved riders.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    


    public function getRiderProfile($id)
    {
        try {
            // Find the rider in the accounts table where role_id = 4
            $rider = Rider::where('id', $id)->where('role_id', 4)->first();
    
            if (!$rider) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Rider not found.',
                ], 404);
            }
    
            // Calculate total delivered amount for the rider (where status is Completed)
            $totalDeliveredAmount = Order::where('rider_id', $id)
                ->where('status', 'Order delivered') // Ensure 'Completed' matches your ENUM values
                ->sum('total_amount');
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Rider profile retrieved successfully.',
                'rider' => [
                    'id' => $rider->id,
                    'rider_name' => $rider->first_name . ' ' . $rider->last_name,
                    'email' => $rider->email,
                    'phone_number' => $rider->phone_number, // Include phone number

                    'role_id' => $rider->role_id
                ],
                'total_delivered_amount' => '₱' . number_format($totalDeliveredAmount, 2) // Add Peso sign and format to 2 decimal places
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve rider profile.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateRiderAvatar(Request $request)
{
    $rider = auth()->user(); // Assumes riders use the same auth guard

    $request->validate([
        'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    try {
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $fileName = 'RiderAvatar-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $request->file('avatar')->getClientOriginalExtension();
            $request->file('avatar')->move(public_path('avatars/riders'), $fileName);
            $fileUrl = url('avatars/riders/' . $fileName);

            DB::table('riders')->where('id', $rider->id)->update([
                'avatar' => $fileUrl,
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Rider avatar uploaded successfully.',
                'avatar_url' => $fileUrl,
            ]);
        } else {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid avatar file.',
            ], 400);
        }
    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to upload rider avatar.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function getPendingRiders()
    {
        try {
            $riders = Rider::where('status', 'Pending')->get();
    
            if ($riders->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No pending riders found.',
                ], 404);
            }
    
            // Format the riders list with full name
            $formattedRiders = $riders->map(function ($rider) {
                return [
                    'id' => $rider->id,
                    'rider_name' => $rider->first_name . ' ' . $rider->last_name,
                    'email' => $rider->email,
                    'phone_number' => $rider->phone_number,
                    'license' => $rider->license,
                    'valid_id' => $rider->valid_id,
                    'status' => $rider->status,
                    // Add any other fields you want to include
                ];
            });
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Pending riders retrieved successfully.',
                'data' => $formattedRiders,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve pending riders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    

    public function applyRider(Request $request)
    {
        try {
            // Validate request input
            $validated = $request->validate([
                'first_name'    => 'required|string|max:255',
                'last_name'     => 'required|string|max:255',
                'email'         => 'required|email|unique:riders,email',
                'password'      => 'required|string|min:9',
                'phone_number'  => 'required|string|max:20',
                'license'       => 'required|image|mimes:jpg,png,jpeg|max:2048',
                'valid_id'      => 'required|image|mimes:jpg,png,jpeg|max:2048',
            ]);
    
            // Ensure directories exist
            $licenseDirectory = public_path('img/licenses');
            $validIdDirectory = public_path('img/valid_ids');
    
            if (!file_exists($licenseDirectory)) {
                mkdir($licenseDirectory, 0755, true);
            }
    
            if (!file_exists($validIdDirectory)) {
                mkdir($validIdDirectory, 0755, true);
            }
    
            // Generate unique file names
            $licenseFileName = 'License-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $request->file('license')->getClientOriginalExtension();
            $validIdFileName = 'ValidID-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $request->file('valid_id')->getClientOriginalExtension();
    
            // Move files
            $request->file('license')->move($licenseDirectory, $licenseFileName);
            $request->file('valid_id')->move($validIdDirectory, $validIdFileName);
    
            // Store paths
            $licensePath = asset('img/licenses/' . $licenseFileName);
            $validIdPath = asset('img/valid_ids/' . $validIdFileName);
    
            // Create rider
            $rider = Rider::create([
                'first_name'   => $validated['first_name'],
                'last_name'    => $validated['last_name'],
                'email'        => $validated['email'],
                'password'     => bcrypt($validated['password']),
                'phone_number' => $validated['phone_number'],
                'license'      => $licensePath,
                'valid_id'     => $validIdPath,
                'status'       => 'Pending',
                'role_id'      => 4
            ]);
    
            // ✅ Send email notification
            $fullName = trim("{$rider->first_name} {$rider->last_name}");
    
            $emailBody = "Hello {$fullName},\n\n";
            $emailBody .= "Thank you for applying as a rider!\n\n";
            $emailBody .= "Your application has been received and is currently under review. We'll notify you once it's approved.\n\n";
            $emailBody .= "Submitted Details:\n";
            $emailBody .= "Email: {$rider->email}\n";
            $emailBody .= "Phone Number: {$rider->phone_number}\n";
            $emailBody .= "Status: {$rider->status}\n\n";
            $emailBody .= "If you have any questions, feel free to contact our support team.\n\n";
            $emailBody .= "Best regards,\nThe Pagsasaka Team";
    
            try {
                Mail::raw($emailBody, function ($message) use ($rider, $fullName) {
                    $message->to($rider->email, $fullName)
                            ->subject('Rider Application Received');
                });
    
                // 📘 Log email success
                Log::info("Rider application email sent successfully to {$rider->email}");
            } catch (\Exception $mailEx) {
                // ❌ Log email failure
                Log::error("Failed to send rider application email to {$rider->email}. Error: " . $mailEx->getMessage());
            }
    
            // Prepare response
            $response = [
                'isSuccess' => true,
                'message'   => 'Application submitted successfully. Waiting for approval.',
                'rider'     => $rider->makeHidden(['password']),
            ];
    
            // Log API call
            $this->logAPICalls('applyRider', $rider->id, $request->all(), $response);
    
            return response()->json($response, 201);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message'   => 'Failed to submit application.',
                'error'     => $e->getMessage(),
            ];
    
            // Log API failure
            $this->logAPICalls('applyRider', null, $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    

    


    

    public function approveRider($id)
    {
        try {
            $rider = Rider::findOrFail($id);
    
            if ($rider->status === 'Approve') {
                $response = ['message' => 'Rider is already approved.'];
                $this->logAPICalls('approveRider', $rider->id, ['id' => $id], $response);
                return response()->json($response, 400);
            }
    
            $rider->update(['status' => 'Approve']);
    
            // ✅ Send approval email notification
            $fullName = trim("{$rider->first_name} {$rider->last_name}");
    
            $emailBody = "Hello {$fullName},\n\n";
            $emailBody .= "Congratulations! Your rider application has been approved.\n\n";
            $emailBody .= "You can now access your rider account and start accepting deliveries.\n\n";
            $emailBody .= "If you have any questions, feel free to contact this person.\n\n";
            $emailBody .= "https://web.facebook.com/kurtsteven.arciga.7";
    
            try {
                Mail::raw($emailBody, function ($message) use ($rider, $fullName) {
                    $message->to($rider->email, $fullName)
                            ->subject('Rider Application Approved');
                });
    
                // 📘 Log email success
                Log::info("Approval email sent successfully to {$rider->email}");
            } catch (\Exception $mailEx) {
                // ❌ Log email failure
                Log::error("Failed to send approval email to {$rider->email}. Error: " . $mailEx->getMessage());
            }
    
            $response = [
                'message' => 'Rider approved successfully.',
                'rider'   => $rider,
            ];
    
            // Log API call
            $this->logAPICalls('approveRider', $rider->id, ['id' => $id], $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'message' => 'Failed to approve rider.',
                'error'   => $e->getMessage(),
            ];
    
            $this->logAPICalls('approveRider', null, ['id' => $id], $response);
    
            return response()->json($response, 500);
        }
    }
    

public function invalidateRider($id)
{
    $rider = Rider::find($id);

    if (!$rider) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Rider not found.',
        ], 404);
    }

    if ($rider->status === 'Invalid') {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Rider is already marked as invalid.',
        ], 400);
    }

    $rider->status = 'Invalid';
    $rider->save();

    return response()->json([
        'isSuccess' => true,
        'message' => 'Rider has been marked as invalid.',
        'rider' => $rider,
    ], 200);
}






public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
{
    try {
        ApiLog::create([
            'method_name' => $methodName,
            'user_id' => $userId,
            'api_request' => json_encode($param),
            'api_response' => json_encode($resp)
        ]);
    } catch (Throwable $e) {
        return false;
    }
    return true;
}


    
}
