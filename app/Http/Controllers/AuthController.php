<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
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


class AuthController extends Controller
{
    public function login(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Get User-Agent from headers
        $userAgent = $request->header('User-Agent');
        Log::info('User-Agent:', ['userAgent' => $userAgent]);

        // Detect platform
        $platformType = $this->detectPlatform($userAgent);
        Log::info('Detected Platform:', ['platform' => $platformType]);

        // Get IP address
        $ipAddress = $request->ip(); // Get the real IP address

        $user = Account::where('email', $request->email)->first();

        // Check if the user exists
        if ($user) {
            // Verify the password
            if (Hash::check($request->password, $user->password)) {
                if ($user->status === 'I') {
                    $response = ['message' => 'Account is inactive.'];
                    $this->logAPICalls('login', $user->email, $request->except(['password']), $response);
                    return response()->json($response, 403);
                }

                // Generate token
                $token = $user->createToken('auth-token')->plainTextToken;

                // Get organizational log name
                $org_log = OrganizationalLog::where('id', $user->org_log_id)->first();
                $org_log_name = optional($org_log)->org_log_name;

                // Prepare response data
                $response = [
                    'isSuccess' => true,
                    'message' => 'Logged in successfully',
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'Firstname' => $user->Firstname,
                        'Middlename' => $user->Middlename,
                        'Lastname' => $user->Lastname,
                        'org_log_id' => $user->org_log_id,
                        'org_log_name' => optional($org_log)->name, 
                        'email' => $user->email,
                    ],
                    'role' => $user->role,
                    'ipAddress' => $ipAddress, 
                    'platform' => $platformType, 
                ];

                // Log API call
                $this->logAPICalls('login', $user->email, $request->except(['password']), $response);

                // Return success response
                return response()->json($response, 200);
            } else {
                return $this->sendError('Invalid Credentials.');
            }
        } else {
            return $this->sendError('Provided email address does not exist.');
        }
    } catch (Throwable $e) {
        // Handle errors during login
        $response = [
            'isSuccess' => false,
            'message' => 'An error occurred during login.',
            'error' => $e->getMessage(),
        ];

        // Log error
        $this->logAPICalls('login', $request->email ?? 'unknown', $request->except(['password']), $response);

        // Return error response
        return response()->json($response, 500);
    }
}
    private function detectPlatform($userAgent)
    {
        Log::info('Evaluating User-Agent:', ['userAgent' => $userAgent]);

        if (stripos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (stripos($userAgent, 'Macintosh') !== false) {
            return 'macOS';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (stripos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            return 'iOS';
        }

        return 'Unknown'; // Default case
    }


    
    
    


    /////logout//////
    public function logout(Request $request)
    {
        try {
            // Get the authenticated user based on the token
            $user = auth()->user();

            if ($user) {
                // Find the user's active session
                $session = Session::where('user_id', $user->id)
                ->whereNull('logout_date')
                ->latest()
                ->first();

                // Get the bearer token from the request header
                $plainTextToken = $request->bearerToken();

                // Find the token in the database by comparing the hashed value
                $token = PersonalAccessToken::findToken($plainTextToken);

                if ($session) {
                    // Update the session's logout date
                    $session->update([
                        'logout_date' => Carbon::now()->toDateTimeString(),
                    ]);
                }

                if ($token) {
                    // Delete the token from the personal_access_tokens table
                    $token->delete();

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

                return response()->json(['message' => 'Token not found'], 404);
            }

            return response()->json(['message' => 'User not found'], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to log out user',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Method to insert session
    public function insertSession(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'get|string|exists:user_accounts,id'
            ]);

            $sessionCode = Str::uuid();
            $dateTime = Carbon::now()->toDateTimeString();


            Session::create([
                'session_code' => $sessionCode,
                'user_id' => $request->id,
                'login_date' => $dateTime,
                'logout_date' => null
            ]);

            return response()->json(['session_code' => $sessionCode], 200);
        } catch (Throwable $e) {
            return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.', 'error' => $e->getMessage()], 500);
        }
    }


    //////password change////////
    public function changePassword(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'Firstname' => 'nullable|string|max:255', 
                'Lastname' => 'nullable|string|max:255',  
                'Middlename' => 'nullable|string|max:255', 
                'email' => 'nullable|email',  
                'current_password' => ['nullable', 'string',
                    function ($attribute, $value, $fail) use ($request) {
                        // Find the user by ID
                        $user = Account::where('id', $request->id)->first();
    
                        // If the password is set and the current password doesn't match
                        if ($user && $user->password && !Hash::check($value, $user->password)) {
                            return $fail('The current password is incorrect.');
                        }
                    },
                ],
                'new_password' => 'nullable|string|min:8|confirmed', 
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
            $user = Account::select('id', 'Firstname', 'Lastname', 'Middlename', 'email', 'org_log_id', 'password')
                ->where('id', $request->id)
                ->first();
    
            if (!$user) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'User not found.',
                ];
                $this->logAPICalls('changePassword', null, $request->all(), $response);
                return response()->json($response, 500);
            }
    
            // Update user's editable fields if provided
            if ($request->has('Firstname')) {
                $user->Firstname = $request->Firstname; 
            }
            if ($request->has('Lastname')) {
                $user->Lastname = $request->Lastname; 
            }
            if ($request->has('Middlename')) {
                $user->Middlename = $request->Middlename; 
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
    
            // Retrieve the organization log based on the user's org_log_id
            $org_log = OrganizationalLog::where('id', $user->org_log_id)->first();
            $org_log_name = $org_log ? $org_log->org_log_name : 'N/A';
    
            if ($request->filled('new_password')) {
                // If the password is empty (null), skip current_password check
                if ($user->password == null || Hash::check($request->current_password, $user->password)) {
                    // Update password if valid current_password is provided (or if it's blank)
                    $user->password = Hash::make($request->new_password);
                } else {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'The current password is incorrect.',
                    ];
                    $this->logAPICalls('changePassword', $user->id, $request->all(), $response);
                    return response()->json($response, 500);
                }
            }
    
            // Save the updated user data
            $user->save();
    
            $response = [
                'isSuccess' => true,
                'message' => 'User details updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'Firstname' => $user->Firstname,  
                    'Lastname' => $user->Lastname,  
                    'Middlename' => $user->Middlename, 
                    'email' => $user->email,
                    'org_log_id' => $user->org_log_id,
                    'org_log_name' => optional($org_log)->name, 
                ]
            ];
            $this->logAPICalls('changePassword', $user->id, $request->except('org_log_id'), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Error handling
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update user details.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('changePassword', null, $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    
    
    

    // Method to log API calls
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
}
