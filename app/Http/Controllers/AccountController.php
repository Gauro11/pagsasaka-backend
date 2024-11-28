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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Notifications\email;

class AccountController extends Controller
{

    // Create a new user account.pagsasaka
    public function createAccount(Request $request)
{
    try {
        // Validate input
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

        // Ensure the role exists based on ID
        $role = Role::find($request->role);
        if (!$role) {
            $response = [
                'isSuccess' => false,
                'message' => 'Invalid role ID provided.',
            ];
            $this->logAPICalls('createAccount', "", $request->all(), $response);
            return response()->json($response, 404);
        }

        // Create account with the valid role
        $Account = Account::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'role' => $role->name, // Set the role name from the Role model
            'password' => Hash::make($request->password ?? '123456789'),
        ]);

        $response = [
            'isSuccess' => true,
            'message' => 'UserAccount successfully created.',
            'account' => [
                'id' => $Account->id,
                'first_name' => $Account->first_name,
                'last_name' => $Account->last_name,
                'middle_name' => $Account->middle_name,
                'email' => $Account->email,
                'role_id' => $request->role, // Role ID from the request
                'role' => $role->name, // Role name from the Role model
                'created_at' => $Account->created_at,
                'updated_at' => $Account->updated_at
            ]
        ];

        $this->logAPICalls('createAccount', "", $request->all(), [$response]);
        return response()->json($response, 201);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to create the Account.',
            'error' => $e->getMessage()
        ];
        $this->logAPICalls('createAccount', "", $request->all(), $response);
        return response()->json($response, 500);
    }
}


    // Update an existing user account.
    public function updateAccount(Request $request, $id)
    {
        try {
            $account = Account::findOrFail($id);
            // Validation with custom error messages
            $request->validate([
                'first_name' => ['sometimes', 'string'],
                'last_name' => ['sometimes', 'string'],
                'middle_name' => ['sometimes', 'string'],
                'email' => ['sometimes', 'string', 'email', Rule::unique('accounts')->ignore($account->id)],
                'role' => ['sometimes', 'string'],
                'org_log_id' => ['sometimes', 'numeric'],
            ], [
                'email.unique' => 'The email is already taken.',
            ]);

            $account->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'email' => $request->email,
                'role' => $request->role,
                'org_log_id' => $request->org_log_id,
            ]);



            $response = [
                'isSuccess' => true,
                'message' => "Account successfully updated.",
                'user' => [
                    'id' => $account->id,
                    'first_name' => $account->first_name,
                    'middle_name' => $account->middle_name,
                    'last_name' => $account->last_name,
                    'org_log_id' => $account->org_log_id,
                    'email' => $account->email,
                    'role' => $account->role,
                ],
            ];
            $this->logAPICalls('updateaccount', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update account.",
                'errors' => $e->errors()
            ];
            $this->logAPICalls('updateaccount', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function deactivateAccount($id)
    {
        try {
            // Find the account by ID
            $account = Account::findOrFail($id);
    
            // Check if the account is not already archived
            if ($account->is_archived === 0) {
                // Update is_archived to 1 (archive the account)
                $account->update(['is_archived' => 1]);
    
                $response = [
                    'isSuccess' => true,
                    'message' => 'Account has been archived successfully.',
                    'account' => $account
                ];
    
                $this->logAPICalls('changeStatusToInactive', $account->id, [], $response);
    
                return response()->json($response, 200);
            }
    
            // If the account is already archived
            $response = [
                'isSuccess' => false,
                'message' => 'Account is already archived.'
            ];
    
            $this->logAPICalls('changeStatusToInactive', $id, [], $response);
    
            return response()->json($response, 400);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to archive the account.',
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('changeStatusToInactive', $id, [], $response);
    
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
            $account = Account::where('email', $request->email)->first();

            if (!$account) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not found.',
                ];
                $this->logAPICalls('resetPasswordToDefault', null, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Reset the password to the default value
            $defaultPassword = '123456789';
            $account->password = Hash::make($defaultPassword);
            $account->save();

            $response = [
                'isSuccess' => true,
                'message' => 'Password reset to default successfully.',
                'account' => $account
            ];
            $this->logAPICalls('resetPasswordToDefault', $account->id, $request->all(), $response);
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

    //  Read: Get all user accounts.     
    public function getAccounts(Request $request)
    {
        try {
            $validated = $request->validate([
                'paginate' => 'required',
            ]);

            // Initialize the base query
            $query = Account::select('id', 'first_name', 'last_name', 'middle_name', 'email', 'role', 'is_archived', 'org_log_id')
                ->where('is_archived', '0')
                ->orderBy('created_at', 'desc');

            // Apply search term if present
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function ($query) use ($searchTerm) {
                    $query->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhereRaw("CONCAT_WS(' ', last_name, first_name, middle_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhereRaw("CONCAT_WS(' ', first_name, last_name, middle_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhere('email', 'like', "%{$searchTerm}%");
                });
            }

            // Paginated data retrieval
            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            // Transform to add `org_log_name` based on `org_log_id`
            $data->getCollection()->transform(function ($item) {
                $org_log = OrganizationalLog::find($item->org_log_id);
                $item->org_log_name = optional($org_log)->name;
                return $item;
            });

            // Prepare the response with only required pagination metadata
            $response = [
                'isSuccess' => true,
                'accounts' => [
                    'data' => $data->items(),
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ],
            ];

            $this->logAPICalls('getAccounts', "", $request->all(), $response);
            return response()->json($response, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid input. Please ensure all required fields are provided correctly.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve accounts. Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ];

            $this->logAPICalls('getAccounts', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }


    public function getOrganizationLogs()
    {
        try {
            $organizationLogs = OrganizationalLog::select('id', 'name')->get();

            $response = [
                'isSuccess' => true,
                'data' => $organizationLogs
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

    // Log all API calls.
    public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
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
