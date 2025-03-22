<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Rider;
use App\Models\Order;

class RiderController extends Controller
{
    // Get rider profile with total delivered amount
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
    $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|unique:riders,email',
        'password' => 'required|string|min:6',
        'phone_number' => 'required|string|max:20',
        'license' => 'required|image|mimes:jpg,png,jpeg|max:2048', // Validate image
    ]);

    // Store the license image
    $licensePath = $request->file('license')->store('licenses', 'public');

    $rider = Rider::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'phone_number' => $request->phone_number,
        'license' => $licensePath, // Save the image path
        'status' => 'Pending', // Set status as pending
        'role_id' => 4 // Set role_id to 4
    ]);

    return response()->json(['message' => 'Application submitted. Waiting for approval.', 'rider' => $rider], 201);
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


    
}
