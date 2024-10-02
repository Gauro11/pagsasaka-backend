<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\listofuser;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Models\ApiLog;
/*
class userlistController extends Controller
{
    // Log API calls
    public function logAPICalls(string $methodName, ?string $userId,  array $param, array $resp)
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

    /**
      create user
     
    public function create(Request $request)
    {
        try {
          
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required',
                'organization_id' => 'required',
                'role' => 'required',
            ]);

            if ($validator->fails()) {
               
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('create', null, $request->all(), $response);
                
                return response()->json($response, 422);
            }

           
            $user = listofuser::create($request->all());

            $response = [
                'isSuccess' => true,
                'message' => 'User created successfully.',
                'data' => $user
            ];
            $this->logAPICalls('create', $user->id, $request->all(), $response);
            
            return response()->json($response, 201);
        } catch (Throwable $e) {
            
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create user.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('create', null, $request->all(), $response);
            
            return response()->json($response, 500);
        }
    }

    //edit////
   
public function edit(Request $request, $name)
{
    try {
       
        $user = listofuser::find($name);
        if (!$user) {
            $response = [
                'isSuccess' => false,
                'message' => 'User not found.'
            ];
            $this->logAPICalls('edit', $name, $request->all(), $response);
            return response()->json($response, 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required',
            'organization_id' => 'required',
            'role' => 'required',
        ]);

        if ($validator->fails()) {
           
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ];
            $this->logAPICalls('edit', $name, $request->all(), $response);
            return response()->json($response, 422);
        }

       
        $user->update($request->all());

        $response = [
            'isSuccess' => true,
            'message' => 'User updated successfully.',
            'data' => $user
        ];
        $this->logAPICalls('edit', $name, $request->all(), $response);
        
        return response()->json($response, 200);
    } catch (Throwable $e) {
      
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to update user.',
            'error' => $e->getMessage()
        ];
        $this->logAPICalls('edit', $name, $request->all(), $response);
        
        return response()->json($response, 500);
    }
}

//delete//
public function destroy($id)
{
    try {
       
        $user = listofuser::find($id);
        if (!$user) {
            $response = [
                'isSuccess' => false,
                'message' => 'User not found.'
            ];
            $this->logAPICalls('delete', $id, [], $response);
            return response()->json($response, 404);
        }

       
        $user->delete();

      
        $response = [
            'isSuccess' => true,
            'message' => 'User deleted successfully.'
        ];
        $this->logAPICalls('delete', $id, [], $response);
        
        return response()->json($response, 200);
    } catch (Throwable $e) {
     
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to delete user.',
            'error' => $e->getMessage()
        ];
        $this->logAPICalls('delete', $id, [], $response);
        
        return response()->json($response, 500);
    }
}
/////////search//

public function searchuser(Request $request)
{
    try {
       
        $validator = Validator::make($request->all(), [
            'name' => 'required|nullable|string',
           
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ];
            $this->logAPICalls('searchAccount', "", $request->all(), $response);
            return response()->json($response, 422);
        }

        $accounts = listofuser::when($request->name, function ($query, $name) {
            return $query->where('name', 'like', '%' . $name . '%');
        })
        ->get();

        if ($accounts->isEmpty()) {
            $response = [
                'isSuccess' => false,
                'message' => 'No accounts found matching the criteria.',
            ];
            $this->logAPICalls('searchAccount', "", $request->all(), $response);
            return response()->json($response, 404);
        }

        $response = [
            'isSuccess' => true,
            'message' => 'Accounts found.',
            'data' => $accounts
        ];
        $this->logAPICalls('searchAccount', "", $request->all(), $response);
        return response()->json($response, 200);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to search for accounts.',
            'error' => $e->getMessage()
        ];
        $this->logAPICalls('searchAccount', "", $request->all(), $response);
        return response()->json($response, 500);
    }
}
}*/