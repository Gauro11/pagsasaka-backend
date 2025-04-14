<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Rider;
use App\Models\Session;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\OrganizationalLog;
use Laravel\Sanctum\PersonalAccessToken;
use App\Mail\OTPMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;





class AuthController extends Controller
{
    
    public function login(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check if the user exists in the accounts table
        $user = Account::where('email', $request->email)
            ->with('role') // Eager load the role relationship
            ->first();

        // If not found in accounts, check in riders
        $isRider = false;
        if (!$user) {
            $user = Rider::where('email', $request->email)->first();
            $isRider = true; // Flag to indicate rider login

            // Prevent login if rider status is "Pending" or "Invalid"
            if ($user && in_array($user->status, ['Pending', 'Invalid'])) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Your rider account is not allowed to log in. Current status: ' . $user->status,
                ], 403);
            }
        }

        // If user exists and password matches
        if ($user && Hash::check($request->password, $user->password)) {
            $token = $user->createToken('auth-token')->plainTextToken;

            // Generate session code by calling insertSession
            $sessionCode = $this->insertSession($user->id);
            if (!$sessionCode) {
                return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.'], 500);
            }

            // Prepare response
            $response = [
                'isSuccess' => true,
                'message' => 'Logged in successfully',
                'token' => $token,
                'session_code' => $sessionCode,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name ?? null,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ],
                'role_name' => $isRider ? 'Rider' : ($user->role->role ?? 'No Role Assigned'),
                'role_id' => $isRider ? 4 : ($user->role_id ?? null)
            ];

            return response()->json($response, 200);
        }

        // Invalid credentials
        return response()->json([
            'isSuccess' => false,
            'message' => 'Invalid credentials.',
        ], 401);

    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Login failed.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    
    
    
    // logout
    public function logout(Request $request)
    {
        try {
            // Get the authenticated user based on the token
            $user = $request->user();

            if ($user) {
                // Find the user's active session
                $session = Session::where('user_id', $user->id)
                    ->whereNull('logout_date')
                    ->latest()
                    ->first();

                if ($session) {
                    // Update the session's logout date
                    $session->update([
                        'logout_date' => Carbon::now()->toDateTimeString(),
                    ]);
                }

                // Delete the token from the personal_access_tokens table
                $user->currentAccessToken()->delete();

                $response = [
                    'isSuccess' => true,
                    'message' => 'User logged out successfully',
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                ];
                $this->logAPICalls('logoutByToken', $user->id, [], $response);
                return response()->json($response, 200);
            }

            return response()->json(['message' => 'User not found'], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to log out user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // password change
    public function profileUpdate(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'email' => 'nullable|email',
            ]);

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('profileUpdate', null, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Find the user by ID
            $user = Account::find($request->id);

            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not found.',
                ];
                $this->logAPICalls('profileUpdate', null, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Update profile fields
            $user->update($request->only(['first_name', 'last_name', 'middle_name', 'email']));

          

            $response = [
                'isSuccess' => true,
                'message' => 'User details updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_name' => $user->middle_name,
                    'email' => $user->email,
                    'org_log_id' => $user->org_log_id,
                    
                ]
            ];
            $this->logAPICalls('profileUpdate', $user->id, $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Error handling
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update user details.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('profileUpdate', null, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('changePassword', null, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Find the user by ID
            $user = Account::find($request->id);

            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not found.',
                ];
                $this->logAPICalls('changePassword', null, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'The current password is incorrect.',
                ];
                $this->logAPICalls('changePassword', $user->id, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Update the password
            $user->password = Hash::make($request->new_password);
            $user->save();

            $response = [
                'isSuccess' => true,
                'message' => 'Password updated successfully.',
            ];
            $this->logAPICalls('changePassword', $user->id, $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {

            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update password.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('changePassword', null, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    // Method to insert session
    public function insertSession(int $userId)
    {
        try {
            $sessionCode = Str::uuid(); // Generate a unique session code
            $dateTime = Carbon::now()->toDateTimeString();

            // Insert session record into the database
            Session::create([
                'session_code' => $sessionCode,
                'user_id' => $userId,
                'login_date' => $dateTime,
                'logout_date' => null, // Initially set logout_date to null
            ]);

            return $sessionCode; // Return the generated session code
        } catch (Throwable $e) {
            Log::error('Failed to create session.', ['error' => $e->getMessage()]);
            return null; // Return null if session creation fails
        }
    }





    // Method to log API calls
    public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
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
}
