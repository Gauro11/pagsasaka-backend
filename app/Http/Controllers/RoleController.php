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
            $role = Role::select('id', 'name')->get();

            $response = [
                'isSuccess' => true,
                'data' => $role
            ];
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to fetch organization logs.',
                'error' => $e->getMessage()
            ];
            return response()->json($response, 500);
        }
    }
}
