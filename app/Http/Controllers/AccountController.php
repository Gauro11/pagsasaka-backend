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
use App\Mail\OTPMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{

    // Create a new user account.pagsasaka
    public function register(Request $request)
    {
        DB::beginTransaction(); // Start a transaction

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'email' => 'required|email', // Email is required
                'password' => 'required|string|min:8|confirmed', // Password is required
                'role' => 'exists:roles,id', // Ensure the role exists in the roles table
            ]);

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('register', '', $request->all(), $response);
                return response()->json($response, 422);
            }

            // Get the role name from the Role model
            $role = Role::find($request->role);

            // Generate OTP
            $otp = rand(100000, 999999);

            // Create account
            $user = Account::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'email' => $request->email,
                'password' => Hash::make($request->password), // Save the user-provided password
                'role' => $role->name,
                'status' => 'A', // Set default active status
            ]);

            // Save OTP in the database
            DB::table('otps')->insert([
                'email' => $user->email,
                'otp' => $otp,
                'created_at' => now(),
                'expires_at' => now()->addMinutes(10), // Set OTP expiration time
            ]);

            // Send OTP via email
            $htmlContent = "<p>Your OTP is: <strong>$otp</strong></p>";
            $subject = "Your OTP Code";
            $email = $user->email;

            try {
                Mail::send([], [], function ($message) use ($email, $htmlContent, $subject) {
                    $message->to($email)
                        ->subject($subject)
                        ->setBody($htmlContent, 'text/html');
                });
            } catch (\Throwable $e) {
                // Log the error for debugging
                Log::error('Error sending email in register method: ' . $e->getMessage(), [
                    'email' => $email,
                    'otp' => $otp,
                ]);

                throw $e; // Re-throw the exception to trigger the outer catch
            }

            DB::commit(); // Commit the transaction if everything succeeds

            $response = [
                'isSuccess' => true,
                'message' => 'Account registered successfully. An OTP has been sent to your email.',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_name' => $user->middle_name,
                    'email' => $user->email,
                    'role' => $role->name,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ];

            $this->logAPICalls('register', $user->email, $request->except(['password', 'password_confirmation']), $response);

            return response()->json($response, 201);
        } catch (\Throwable $e) {
            DB::rollBack(); // Rollback the transaction on error

            // Log the error for debugging
            Log::error('Error in register method: ' . $e->getMessage(), [
                'request_data' => $request->all(),
            ]);

            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during registration.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('register', $request->email ?? 'unknown', $request->all(), $response);

            return response()->json($response, 500);
        }
    }



    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('verifyOTP', $request->email, $request->all(), $response);
            return response()->json($response, 422);
        }

        try {
            // Fetch the OTP record from the database
            $otpRecord = DB::table('otps')
            ->where('email', $request->email)
                ->where('otp', $request->otp)
                ->first();

            // Check if OTP exists
            if (!$otpRecord) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Invalid OTP or email.',
                ];
                $this->logAPICalls('verifyOTP', $request->email, $request->all(), $response);
                return response()->json($response, 400);
            }

            // Check if OTP has expired
            if (now()->greaterThan($otpRecord->expires_at)) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'OTP has expired.',
                ];
                $this->logAPICalls('verifyOTP', $request->email, $request->all(), $response);
                return response()->json($response, 400);
            }

            // Mark OTP as used or delete it (optional)
            // DB::table('otps')->where('id', $otpRecord->id)->delete();

            $response = [
                'isSuccess' => true,
                'message' => 'OTP verified successfully.',
            ];
            $this->logAPICalls('verifyOTP', $request->email, $request->all(), $response);

            return response()->json($response, 200);
        } catch (\Throwable $e) {
            Log::error('Error verifying OTP: ' . $e->getMessage(), [
                'request_data' => $request->all(),
            ]);

            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during OTP verification.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('verifyOTP', $request->email ?? 'unknown', $request->all(), $response);

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

}
