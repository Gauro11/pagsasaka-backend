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
                'valid_id'      => 'required|image|mimes:jpg,png,jpeg|max:2048', // New validation rule for valid ID
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
            
            // Move files to the respective directories
            $request->file('license')->move($licenseDirectory, $licenseFileName);
            $request->file('valid_id')->move($validIdDirectory, $validIdFileName);
    
            // Store full image URLs
            $licensePath = asset('img/licenses/' . $licenseFileName);
            $validIdPath = asset('img/valid_ids/' . $validIdFileName);
    
            // Create rider entry
            $rider = Rider::create([
                'first_name'   => $validated['first_name'],
                'last_name'    => $validated['last_name'],
                'email'        => $validated['email'],
                'password'     => bcrypt($validated['password']),
                'phone_number' => $validated['phone_number'],
                'license'      => $licensePath, // Save full image URL for license
                'valid_id'     => $validIdPath, // Save full image URL for valid ID
                'status'       => 'Pending',
                'role_id'      => 4
            ]);
    
            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message'   => 'Application submitted successfully. Waiting for approval.',
                'rider'     => $rider->makeHidden(['password']), // Hide the password field
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
