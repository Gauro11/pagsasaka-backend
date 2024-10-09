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

    /*
     * Read: Get all user accounts.
     */
    public function getAccounts(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);

            $datas = Account::select('id', 'name', 'email', 'role', 'status', 'org_log_id')
                ->where('status', 'A')
                ->when($request->search, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('email', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->paginate($perPage);

            if ($datas->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No active accounts found matching the criteria.',
                ];
                $this->logAPICalls('getAccounts', "", $request->all(), $response);
                return response()->json($response, 500);
            }

            $accounts = $datas->map(function ($data) {
                $org_log = OrganizationalLog::where('id', $data->org_log_id)->first();

                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'email' => $data->email,
                    'role' => $data->role,
                    'status' => $data->status,
                    'org_log_id' => $data->org_log_id,
                    'org_log_name' => optional($org_log)->name,
                ];
            });

            // Prepare the response with pagination metadata
            $response = [
                'isSuccess' => true,
                'message' => 'Active user accounts retrieved successfully.',
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
            $account = Account::findOrFail($id);
            // Validation with custom error messages
            $request->validate([
                'name' => ['sometimes', 'string'],
                'email' => ['sometimes', 'string', 'email', Rule::unique('accounts')->ignore($account->id)],
                'role' => ['sometimes', 'string'],
                'org_log_id' => ['sometimes', 'numeric'],
            ], [
                'email.unique' => 'The email is already taken.',
            ]);

            $account->update([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'org_log_id' => $request->org_log_id,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Account successfully updated.",
                'account' => $account
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

    public function changeStatusToInactive($id)
    {
        try {
            $account = Account::findOrFail($id);

            // Ensure status is "A" before updating
            if ($account->status === 'A') {
                $account->update(['status' => 'I']);

                $response = [
                    'isSuccess' => true,
                    'message' => 'Account status is Inactive.',
                    'account' => $account
                ];

                $this->logAPICalls('changeStatusToInactive', $account->id, [], $response);

                return response()->json($response, 200);
            }

            $response = [
                'isSuccess' => false,
                'message' => 'Account is already inactive or not found.'
            ];

            $this->logAPICalls('changeStatusToInactive', $id, [], $response);

            return response()->json($response, 400);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update account status.',
                'error' => $e->getMessage()
            ];

            $this->logAPICalls('changeStatusToInactive', $id, [], $response);

            return response()->json($response, 500);
        }
    }

    /**
     * Delete a user account.
     */
    /*  public function deleteAccount(Request $request, $id)
{
    try {
        
        $Account = Account::findOrFail($id);

       
        $Account->delete();

        // Prepare a successful response
        $response = [
            'isSuccess' => true,
            'message' => "Account successfully deleted.",
            'account' => $Account
        ];

        // Log the API call
        $this->logAPICalls('deleteAccount', $id, $request->all(), [$response]);

        // Return success response
        return response()->json($response, 200);

    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => "Failed to delete the Account.",
            'error' => $e->getMessage(),
        ];

        $this->logAPICalls('deleteAccount', $id, $request->all(), [$response]);

        
        return response()->json($response, 500);
    }
}
*/
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

    /**
     * Log all API calls.
     */ public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
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
