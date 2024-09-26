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

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
           
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
    
           
            $user = Account::where('email', $request->email)->first();
    
            if ($user) {
                
                if (Hash::check($request->password, $user->password)) {
                   
                    $token = null;
                    switch ($user->role) {
                        case 'Admin':
                            $token = $user->createToken('admin-token', ['admin'])->plainTextToken;
                            break;
                        case 'Head':
                            $token = $user->createToken('head-token', ['head'])->plainTextToken;
                            break;
                        case 'Programchair':
                            $token = $user->createToken('programchair-token', ['programchair'])->plainTextToken;
                            break;
                        case 'Staff':
                            $token = $user->createToken('staff-token', ['staff'])->plainTextToken;
                            break;
                        case 'Dean':
                            $token = $user->createToken('dean-token', ['dean'])->plainTextToken;
                            break;
                        default:
                            $response = ['message' => 'Unauthorized'];
                            $this->logAPICalls('login', $request->email, $request->except(['password']), $response);
                            return response()->json($response, 403);
                    }
    
                
                    $sessionResponse = $this->insertSession($request->merge(['id' => $user->id]));
    
                   
                    $response = [
                        'isScucces' => true,
                        'message' => ucfirst($user->role) . ' logged in successfully',
                        'token' => $token,
                        'user' => $user->only(['id', 'email']),
                        'role' => $user->role,
                        'session' => $sessionResponse->getData(),
                    ];
                    $this->logAPICalls('login', $request->email, $request->except(['password']), $response);
                    return response()->json($response, 200);
    
                } else {
                    
                    $response = ['message' => 'Invalid credentials'];
                    $this->logAPICalls('login', $request->email, $request->except(['password']), $response);
                    return response()->json($response, 401); 
                }
            } else {
                $response = ['message' => 'Invalid credentials'];
                $this->logAPICalls('login', $request->email, $request->except(['password']), $response);
                return response()->json($response, 401); 
            }
        } catch (Throwable $e) {
            $response = [
                'message' => 'An error occurred',
                'error' => $e->getMessage() // Return the specific error message
            ];
            $this->logAPICalls('login', $request->email, $request->all(), $response); 
            return response()->json($response, 500);
        }
    }
/////logout//////
public function logout(Request $request)
{
    try {
        $user = $request->user();  // Get the authenticated user

        if ($user) {
            Log::info('User logging out:', ['name' => $user->email]);

            // Find the latest session for this user with a null logout_date
            $session = Session::where('user_id', $user->id)
                              ->whereNull('logout_date') // Find session where logout hasn't been set
                              ->latest()  // Get the latest session
                              ->first();

            if ($session) {
                // Update the session with the current logout date
                $session->update([
                    'logout_date' => Carbon::now()->toDateTimeString(), // Set logout date to current time
                ]);
            }

            // Revoke the user's current access token
            $user->currentAccessToken()->delete();

            $response = ['message' => 'User logged out successfully'];
            $this->logAPICalls('logout', $user->id, [], $response);
            return response()->json($response, 200);
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    } catch (Throwable $e) {
        return response()->json([
            'message' => 'Failed to log out',
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

            return response()->json([ 'message' => 'Session successfully created.', 'session_code' => $sessionCode], 201);

        } catch (Throwable $e) {
            return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.', 'error' => $e->getMessage()], 500);
        }
    }

    
  


   

    

//////password reset////////

    public function resetPassword(Request $request)
{
    try {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'new_password' => 'required|min:8',
            
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('resetPassword', null, $request->all(), $response);
            return response()->json($response, 422);
        }

        // Find the user by email
        $user = Account::where('email', $request->email)->first();

        if (!$user) {
            $response = [
                'isSuccess' => false,
                'message' => 'User not found.',
            ];
            $this->logAPICalls('resetPassword', null, $request->all(), $response);
            return response()->json($response, 404);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Successful response
        $response = [
            'isSuccess' => true,
            'message' => 'Password reset successfully.',
        ];
        $this->logAPICalls('resetPassword', $user->id, $request->all(), $response);
        return response()->json($response, 200);

    } catch (Throwable $e) {
        // Error handling
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to reset password.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('resetPassword', null, $request->all(), $response);
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


