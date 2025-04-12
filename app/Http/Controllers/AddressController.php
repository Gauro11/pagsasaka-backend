<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Account;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    // Get all addresses for a specific account
    public function index($accountId)
    {
        $addresses = Address::where('account_id', $accountId)->get();
        return response()->json($addresses);
    }

    // Add a new address for a specific account
    public function store(Request $request, $accountId)
{
    // Validate the request
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'address' => 'required|string',
        'is_default' => 'boolean',
        'is_billing' => 'boolean',
    ]);

    // Check if the account exists
    $account = Account::findOrFail($accountId);

    // Create the address
    $newAddress = Address::create([
        'account_id' => $accountId,
        'name' => $validated['name'],
        'address' => $validated['address'],
        'is_default' => $request->input('is_default', false),
        'is_billing' => $request->input('is_billing', false),
    ]);

    return response()->json($newAddress, 201);
}

    // Update an address (set as billing or default)
    public function update(Request $request, $accountId, $addressId)
    {
        $address = Address::where('account_id', $accountId)
                         ->where('id', $addressId)
                         ->firstOrFail();

        $address->update([
            'is_billing' => $request->input('isBilling', $address->is_billing),
            'is_default' => $request->input('isDefault', $address->is_default),
        ]);

        return response()->json(['message' => 'Address updated']);
    }

    // Delete an address
    public function destroy($accountId, $addressId)
    {
        $address = Address::where('account_id', $accountId)
                         ->where('id', $addressId)
                         ->firstOrFail();

        $address->delete();
        return response()->json(['message' => 'Address deleted']);
    }
}