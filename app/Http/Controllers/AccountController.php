<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\role;
use App\Models\CollegeOffice;
use App\Models\OrganizationalLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Notifications\email;
use App\Mail\OTPMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

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
            'email' => 'required|email|unique:accounts,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,id',
            'security_question_id' => 'required|exists:questions,id',
            'security_answer' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'delivery_address' => 'required|string|max:500',
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

        // Generate OTP
        $otp = rand(100000, 999999);

        // Create account
        $user = Account::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role,
            'security_id' => $request->security_question_id,
            'security_answer' => Hash::make($request->security_answer),
            'status' => 'A',
            'phone_number' => $request->phone_number,
            'delivery_address' => $request->delivery_address,
        ]);
        $user->load('role');

        // Save OTP in the database
        DB::table('otps')->insert([
            'email' => $user->email,
            'otp' => $otp,
            'created_at' => now(),
            'expires_at' => now()->addMinutes(10),
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
        } catch (Throwable $e) {
            Log::error('Error sending email in register method: ' . $e->getMessage(), [
                'email' => $email,
                'otp' => $otp,
            ]);
            throw $e;
        }

        DB::commit();

        $response = [
            'isSuccess' => true,
            'message' => 'Account registered successfully. An OTP has been sent to your email.',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_name' => $user->role ? $user->role->role : null,
                'phone_number' => $user->phone_number,
                'delivery_address' => $user->delivery_address,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ];

        $this->logAPICalls('register', $user->email, $request->except(['password', 'password_confirmation']), $response);

        return response()->json($response, 201);
    } catch (Throwable $e) {
        DB::rollBack();

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
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('verifyOTP', "", $request->all(), $response);
            return response()->json($response, 422);
        }

        try {
            // Fetch the latest OTP record from the database
            $otpRecord = DB::table('otps')
                ->orderBy('created_at', 'desc') // Fetch the most recent OTP
                ->first();

            // Check if OTP record exists
            if (!$otpRecord) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No OTP found in the database.',
                ];
                $this->logAPICalls('verifyOTP', "", $request->all(), $response);
                return response()->json($response, 404);
            }

            // Validate the provided OTP
            if ($otpRecord->otp != $request->otp) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Invalid OTP.',
                ];
                $this->logAPICalls('verifyOTP', "", $request->all(), $response);
                return response()->json($response, 400);
            }

            // Check if the OTP has expired
            if (now()->greaterThan($otpRecord->expires_at)) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'OTP has expired.',
                ];
                $this->logAPICalls('verifyOTP', "", $request->all(), $response);
                return response()->json($response, 400);
            }

            // Mark OTP as used or delete it (optional)
            DB::table('otps')->where('id', $otpRecord->id)->delete();

            $response = [
                'isSuccess' => true,
                'message' => 'OTP verified successfully.',
            ];
            $this->logAPICalls('verifyOTP', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            Log::error('Error verifying OTP: ' . $e->getMessage(), [
                'request_data' => $request->all(),
            ]);

            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during OTP verification.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('verifyOTP', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Update an existing user account.
    public function updateAccount(Request $request, $id)
    {
        try {
            $account = Account::findOrFail($id);

            // Validation with custom error messages (password removed)
            $request->validate([
                'first_name' => ['sometimes', 'string', 'max:225'],
                'last_name' => ['sometimes', 'string', 'max:225'],
                'middle_name' => ['sometimes', 'string', 'max:225', 'nullable'],
                'email' => ['sometimes', 'string', 'email', 'max:225', Rule::unique('accounts')->ignore($account->id)],
                'role_id' => ['sometimes', 'numeric', 'exists:roles,id'],
                'phone_number' => ['sometimes', 'string', 'max:225', 'nullable'],
                'security_answer' => ['sometimes', 'string', 'max:225', 'nullable'],
                'avatar' => ['sometimes', 'string', 'max:225', 'nullable'],
                'delivery_address' => ['sometimes', 'string', 'max:225', 'nullable'],
            ], [
                'email.unique' => 'The email is already taken.',
                'role_id.exists' => 'The selected role does not exist.',
            ]);

            // Prepare the data to update (password removed)
            $updateData = [
                'first_name' => $request->input('first_name', $account->first_name),
                'last_name' => $request->input('last_name', $account->last_name),
                'middle_name' => $request->input('middle_name', $account->middle_name),
                'email' => $request->input('email', $account->email),
                'role_id' => $request->input('role_id', $account->role_id),
                'phone_number' => $request->input('phone_number', $account->phone_number),
                'security_answer' => $request->input('security_answer') ? Hash::make($request->security_answer) : $account->security_answer,
                'avatar' => $request->input('avatar', $account->avatar),
                'delivery_address' => $request->input('delivery_address', $account->delivery_address),
            ];

            // Update the account
            $account->update($updateData);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Account successfully updated.",
                'user' => [
                    'id' => $account->id,
                    'first_name' => $account->first_name,
                    'middle_name' => $account->middle_name,
                    'last_name' => $account->last_name,
                    'email' => $account->email,
                    'role_id' => $account->role_id,
                    'phone_number' => $account->phone_number,
                    'avatar' => $account->avatar,
                    'delivery_address' => $account->delivery_address,
                    'is_archived' => $account->is_archived,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ],
            ];

            $this->logAPICalls('updateaccount', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update account due to validation errors.",
                'errors' => $e->errors(),
            ];
            $this->logAPICalls('updateaccount', $id, $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update account due to an unexpected error.",
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateaccount', $id, $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    //change passwrod

    public function updatePassword(Request $request, $id)
    {
        try {
            $account = Account::findOrFail($id);

            // Validation for password
            $request->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ], [
                'password.required' => 'The password field is required.',
                'password.string' => 'The password must be a string.',
                'password.min' => 'The password must be at least 8 characters.',
                'password.confirmed' => 'The password confirmation does not match.',
            ]);

            // Update the password
            $account->update([
                'password' => Hash::make($request->password),
            ]);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Password successfully updated.",
                'user' => [
                    'id' => $account->id,
                    'email' => $account->email,
                    'updated_at' => $account->updated_at,
                ],
            ];

            $this->logAPICalls('updatepassword', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update password due to validation errors.",
                'errors' => $e->errors(),
            ];
            $this->logAPICalls('updatepassword', $id, $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update password due to an unexpected error.",
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updatepassword', $id, $request->all(), [$response]);
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
            $perPage = $request->input('per_page', 5);
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

    public function addBillingAddress(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $validatedData = $request->validate([
                'address_line1' => 'required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'required|string|max:100',
                'province' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
            ]);

            // Fetch the user's existing account
            $account = Account::where('id', $user->id)->first();

            if (!$account) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Account not found for the user.',
                ], 404);
            }

            // Update the billing address info in the account
            $account->update($validatedData);

            // Return only selected fields
            $billingAddress = $account->only([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'address_line1',
                'address_line2',
                'city',
                'province',
                'postal_code',
                'country',
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Billing address created successfully.',
                'billing_address' => $billingAddress,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while creating the billing address.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function editBillingAddress(Request $request, $id)
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }
    
        try {
            $validated = $request->validate([
                'address_line1' => 'required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'required|string|max:100',
                'province' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
            ]);
    
            // Get the record ensuring the user owns it
            $billingAddress = Account::where('id', $id)
                ->firstOrFail();
    
            $billingAddress->update($validated);
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Billing address updated successfully.',
                'billing_address' => [
                    'id' => $billingAddress->id,
                    'first_name' => $billingAddress->first_name,
                    'middle_name' => $billingAddress->middle_name,
                    'last_name' => $billingAddress->last_name,
                    'phone_number' => $billingAddress->phone_number,
                    'address_line1' => $billingAddress->address_line1,
                    'address_line2' => $billingAddress->address_line2,
                    'city' => $billingAddress->city,
                    'province' => $billingAddress->province,
                    'postal_code' => $billingAddress->postal_code,
                    'country' => $billingAddress->country,
                ],
            ], 200);
    
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Billing address not found for this user.',
            ], 404);
        } catch (ValidationException $v) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while updating the billing address.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeBillingAddress(Request $request, $id)
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }
    
        try {
            // Find the account record that belongs to this user
            $billingAddress = Account::where('id', $id)
                ->firstOrFail();
    
            // Clear out billing address fields (if you're not deleting the row)
            $billingAddress->update([
                'address_line1' => '',
                'address_line2' => '',
                'city' => '',
                'province' => '',
                'postal_code' => '',
                'country' => '',
            ]);
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Billing address removed successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Billing address not found for this user.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while removing the billing address.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function listBillingAddress()
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }
    
        try {
            $account = Account::where('id', $user->id)->first();
    
            if (!$account) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Account not found for the user.',
                ], 404);
            }
    
            $billingAddress = $account->only([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'phone_number',
                'address_line1',
                'address_line2',
                'city',
                'province',
                'postal_code',
                'country',
            ]);
    
            return response()->json([
                'isSuccess' => true,
                'billing_address' => $billingAddress,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while fetching the billing address.',
                'error' => $e->getMessage(),
            ], 500);
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

