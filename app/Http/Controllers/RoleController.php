<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Throwable;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{   
    public function getRoles()
    {
        try {
            // Fetch roles excluding "Admin"
            $role = Role::select('id', 'name')
                ->where('name', '!=', 'Admin')
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
