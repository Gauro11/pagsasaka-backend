<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\role;
use App\Models\CollegeOffice;
use App\Models\OrganizationalLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\ValidationException;

class AccountController extends Controller
{
    /**
     * Create a new user account.
     */
    public function createAccount(Request $request)
    {
        try {
            $validator = Account::validateAccount($request->all());

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('createAccount', "", $request->all(), $response);
                return response()->json($response, 500);
            }

            $Account = Account::create([
                
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'org_log_id' => $request->org_log_id,
              'password' => Hash::make($request->password ?? '123456789'),
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully created.',
                'Account' => $Account
            ];
            $this->logAPICalls('createAccount', $Account->id, $request->all(), $response);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Account.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createAccount', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

     /*
     * Read: Get all user accounts.
     */
    public function getAccounts(Request $request)
    {
        try {
            // Get the per_page value from the request, defaulting to 10 if not provided
            $perPage = $request->input('per_page', 10);
    
            // Fetch accounts with optional search parameter for name
            $datas = Account::select('id', 'name', 'email', 'role', 'status', 'org_log_id')
                ->when($request->search, function ($query, $name) {
                    return $query->where('name', 'like', '%' . $name . '%')
                   ->orWhere('email', 'like', '%' . $name . '%');
                })
                ->paginate($perPage);
    
            if ($datas->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No accounts found matching the criteria.',
                ];
                $this->logAPICalls('getAccounts', "", $request->all(), $response);
                return response()->json($response, 500);
            }
    
            $accounts = $datas->map(function ($data) {
                // Find the organizational log by ID
                $org_log = OrganizationalLog::where('id', $data->org_log_id)->first();
    
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'email' => $data->email,
                    'role' => $data->role,
                    'status' => $data->status,
                    'org_log_id' => $data->org_log_id,
                    'org_log_name' => optional($org_log)->name,  // Use optional() to avoid errors if org_log is null
                ];
            });
    
            // Prepare the response including the pagination metadata
            $response = [
                'isSuccess' => true,
                'message' => 'User accounts retrieved successfully.',
                'Accounts' => $accounts,
                'pagination' => [
                    'current_page' => $datas->currentPage(),
                    'per_page' => $datas->perPage(),
                    'total' => $datas->total(),
                    'last_page' => $datas->lastPage(),
                ],
            ];
    
            $this->logAPICalls('getAccounts', "", $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve accounts.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('getAccounts', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    
   
    /**
     * Update an existing user account.
     */ 
    public function updateAccount(Request $request, $id)
    {
        try {
            $Account = Account::findOrFail($id);
    
            // Validation will throw a ValidationException automatically if it fails
            $request->validate([
                'name' => ['sometimes','required', 'string'],
                'email' => ['sometimes','required', 'string'],
                'role' => ['sometimes','required', 'string'],
                'org_log_id'=> ['sometimes', 'required', 'numeric'],
                
            ]);
    
            $Account->update([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->email,
                'org_log_id' => $request->org_log_id,
            ]);
               
            
    
            $response = [
                'isSuccess' => true,
                'message' => "Account successfully updated.",
                'account' => $Account
            ];
            $this->logAPICalls('updatemanpower', $id, $request->all(), [$response]);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Handle non-validation errors
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Manpower.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updatemanpower', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }
    
    

    /**
     * Delete a user account.
     */
    public function deleteAccount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('deleteAccount', null, $request->all(), $response);
                return response()->json($response, 500);
            }
    
            // Find the user account by name
            $userAccount = Account::where('name', $request->name)->first();
    
            // Check if the account exists
            if (!$userAccount) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Account not found.',
                ];
                $this->logAPICalls('deleteAccount', null, $request->all(), $response);
                return response()->json($response, 500);
            }
    
            // Delete the user account
            $userAccount->delete();
    
            $response = [
                'isSuccess' => true,
                'message' => 'Account successfully deleted.'
            ];
    
            $this->logAPICalls('deleteUserAccount', $userAccount->id, [], $response);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the Account.',
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('deleteAccount', null, $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    
    
    public function resetPasswordToDefault(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
            ]);
    
            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('resetPasswordToDefault', null, $request->all(), $response);
                return response()->json($response, 500);
            }
    
            // Find the user by email
            $user = Account::where('email', $request->email)->first();
    
            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not found.',
                ];
                $this->logAPICalls('resetPasswordToDefault', null, $request->all(), $response);
                return response()->json($response, 500);
            }
    
            // Reset the password to the default value
            $defaultPassword = '123456789';
            $user->password = Hash::make($defaultPassword);
            $user->save();
    
            $response = [
                'isSuccess' => true,
                'message' => 'Password reset to default successfully.',
            ];
            $this->logAPICalls('resetPasswordToDefault', $user->id, $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Error handling
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to reset password.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('resetPasswordToDefault', null, $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    

    /**
     * Log all API calls.
     */public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
{
    
    try {
        // If userId is null, use a default value for logging
        $userId = $userId ?? 'N/A'; // Or any default placeholder you'd prefer

        \App\Models\ApiLog::create([
            'method_name' => $methodName,
            'user_id' => $userId,
            'api_request' => json_encode($param),
            'api_response' => json_encode($resp),
        ]);
    } catch (Throwable $e) {
       
     
        return false; // Indicate failure
    }
    return true; // Indicate success
}


    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////
   /* public function __construct(Request $request)
    {
        // Retrieve the authenticated user
        $user = $request->user();

        // Apply middleware based on the user type
        if ($user && $user->user_type === 'Admin') {
            $this->middleware('UserTypeAuth:Admin')->only(['createAccount', 'createAccount']);
        }

        if ($user && $user->user_type === 'Programchair') {
            $this->middleware('UserTypeAuth:Progamchair')->only(['updateAccount','getAccounts']);
        }

        if ($user && $user->user_type === 'Head') {
            $this->middleware('UserTypeAuth:Head')->only(['updateReview','getReviews']);
        }

        if ($user && $user->user_type === 'Dean') {
            $this->middleware('UserTypeAuth:Dean')->only(['updateReview','getReviews']);
        }

        if ($user && $user->user_type === 'Staff') {
            $this->middleware('UserTypeAuth:Staff')->only(['getReviews']);
        }
    }*/
}
