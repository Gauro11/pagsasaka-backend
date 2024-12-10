<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Shipment;

class ShipmentController extends Controller
{
    public function addShipment(Request $request)
{
    try {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'ship_to' => 'required|string|max:255',
            'status' => 'required|in:pending,shipped,delivered,canceled', // Adjust statuses as per your needs
        ]);

        if ($validator->fails()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create a new shipment
        $shipment = Shipment::create([
            'name' => $request->name,
            'ship_to' => $request->ship_to,
            'status' => $request->status,
        ]);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Shipment added successfully.',
            'shipment' => $shipment,
        ], 201);
    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'An error occurred while adding the shipment.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
