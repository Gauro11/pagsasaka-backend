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
    
            // Auto-detect User-Agent
            $userAgent = $request->header('User-Agent');
            Log::info('User-Agent:', ['userAgent' => $userAgent]);
    
            // Detect platform automatically
            $platformType = $this->detectPlatform($userAgent); // Pass the User-Agent to detectPlatform
            Log::info('Detected Platform:', ['platform' => $platformType]);
    
            // Get IP address
            $ipAddress = $request->ip();
    
            // Determine file system based on the detected platform
            $fileSystemType = $this->detectFileSystem($platformType); // Call a separate method for file system detection
    
            $user = Account::where('email', $request->email)->first();
    
            if ($user && Hash::check($request->password, $user->password)) {    
                if ($user->status === 'I') {
                    $response = ['message' => 'Account is inactive.'];
                    $this->logAPICalls('login', $user->email, $request->except(['password']), $response);
                    return response()->json($response, 403);
                }
    
                $token = $user->createToken('auth-token')->plainTextToken;

                // Generate session code by calling insertSession
                $sessionCode = $this->insertSession($user->id);
                if (!$sessionCode) {
                    return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.'], 500);
                }

                $org_log = OrganizationalLog::where('id', $user->org_log_id)->first();
              

            
                $response = [

                    'isSuccess' => true,
                    'message' => 'Logged in successfully',
                    'token' => $token,
                    'session_code' => $sessionCode,

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
                    'fileSystem' => $fileSystemType, // Automatically set file system
                ];
    
                // Log successful login attempt
                $this->logAPICalls('login', $user->email, $request->except(['password']), $response);
    
                return response()->json($response, 200);
            } else {
                // Log invalid credentials attempt
                $response = ['message' => 'Invalid Credentials.'];
                $this->logAPICalls('login', $request->email ?? 'unknown', $request->except(['password']), $response);
                return response()->json($response, 401);
            }
        } catch (Throwable $e) {
            // Log error during login attempt
            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during login.',
                'error' => $e->getMessage(),
            ];
            
            $this->logAPICalls('login', $request->email ?? 'unknown', $request->except(['password']), $response);
            
            return response()->json($response, 500);
        }
    }
    
    // Separate function for file system detection
    private function detectFileSystem($platformType)
    {
        switch (strtolower($platformType)) {
            case 'windows':
                return 'NTFS';
            case 'macos':
                return 'APFS';
            case 'linux':
                return 'exFAT'; // Adjust this if you have a different logic for Linux
            default:
                return 'Unknown'; // Default for any unrecognized platform
        }
    }
    
    // Modify the detectPlatform method
    private function detectPlatform($userAgent)
    {
        $userAgent = strtolower($userAgent); // Convert to lowercase for easier matching
    
        if (strpos($userAgent, 'windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'macintosh') !== false || strpos($userAgent, 'mac os') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        } else {
            return 'Unknown'; // Return unknown for unrecognized user agents
        }
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
    public function insertSession(int $userId) // Accept an integer instead of Request
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



    //////password change////////
    public function changePassword(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'current_password' => [
                    'nullable',
                    'string',
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
            $user = Account::select('id', 'first_name', 'last_name', 'middle_name', 'email', 'org_log_id', 'password')
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
            if ($request->has('first_name')) {
                $user->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $user->last_name = $request->last_name;
            }
            if ($request->has('middle_name')) {
                $user->middle_name = $request->middle_name;
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
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_name' => $user->middle_name,
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
