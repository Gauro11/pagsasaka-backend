<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Rider;
use App\Models\Order;
use App\Models\ApiLog;
use Throwable;

class RiderController extends Controller
{
    // Get rider profile with total delivered amount//
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
                    'first_name' => $rider->first_name,
                    'last_name' => $rider->last_name,
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
        ]);

        // Ensure the directory exists
        $directory = public_path('img/licenses');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate a unique file name
        $fileName = 'License-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $request->file('license')->getClientOriginalExtension();
        
        // Move file to the directory
        $request->file('license')->move($directory, $fileName);

        // Store the full image URL
        $licensePath = asset('img/licenses/' . $fileName);

        // Create rider entry
        $rider = Rider::create([
            'first_name'   => $validated['first_name'],
            'last_name'    => $validated['last_name'],
            'email'        => $validated['email'],
            'password'     => bcrypt($validated['password']),
            'phone_number' => $validated['phone_number'],
            'license'      => $licensePath, // Save full image URL
            'status'       => 'Pending',
            'role_id'      => 4
        ]);

        // Prepare success response
        $response = [
            'isSuccess' => true,
            'message'   => 'Application submitted successfully. Waiting for approval.',
            'rider_id'  => $rider->id,
            'license_url' => $licensePath,
        ];

        // Log the API call
        $this->logAPICalls('applyRider', $rider->id, $request->all(), $response);

        return response()->json($response, 201);

    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message'   => 'Failed to submit application.',
            'error'     => $e->getMessage(),
        ];

        // Log the API call
        $this->logAPICalls('applyRider', null, $request->all(), $response);

        return response()->json($response, 500);
    }
}

    

public function approveRider($id)
{
    $rider = Rider::findOrFail($id);
    
    if ($rider->status === 'Approve') {
        return response()->json(['message' => 'Rider is already approved.'], 400);
    }

    $rider->update(['status' => 'Approve']);

    return response()->json(['message' => 'Rider approved successfully.', 'rider' => $rider], 200);
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
