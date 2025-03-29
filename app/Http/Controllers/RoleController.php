<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{   
    public function addRole(Request $request)
    {
        try {
            // Validate the input
            $validated = $request->validate([
                'role' => 'required|string|unique:roles,role', // Updated column name
            ]);
    
            // Create a new role
            $role = Role::create([
                'role' => $validated['role'], // Updated field
            ]);
    
            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Role successfully added.',
                'data' => $role,
            ];
    
            // Log the successful operation
            Log::info('Role successfully added', ['role' => $role]);
    
            return response()->json($response, 201);
        } catch (ValidationException $e) {
            // Handle validation errors
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error. Please ensure the data is correct.',
                'errors' => $e->errors(),
            ];
    
            Log::warning('Validation error in addRole', ['errors' => $e->errors()]);
    
            return response()->json($response, 422);
        } catch (Throwable $e) {
            // Handle unexpected errors
            Log::error('Error adding role: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
    
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to add role. Please try again later.',
                'error' => $e->getMessage(),
            ];
    
            return response()->json($response, 500);
        }
    }
    




    public function getRoles()
    {
        try {
            // Fetch roles excluding "Admin" and "Rider"
            $role = Role::select('id', 'role')
                ->whereNotIn('role', ['Admin', 'Rider'])
                ->get();
    
            $response = [
                'isSuccess' => true,
                'data' => $role
            ];
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to fetch roles.',
                'error' => $e->getMessage()
            ];
            return response()->json($response, 500);
        }
    }
    
    
}
