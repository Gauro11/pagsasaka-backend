<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Session;

class CheckSessionExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
       // Check if the user is authenticated
       if (Auth::check()) {
        $user = Auth::user();

        // Get the active session for the user (where logout_date is null)
        $session = Session::where('user_id', $user->id)
                          ->whereNull('logout_date')
                          ->latest()
                          ->first();

        if ($session) {
            $loginTime = Carbon::parse($session->login_date);
            $currentTime = Carbon::now();

            // Check if more than 60 minutes have passed since login
            if ($loginTime->diffInMinutes($currentTime) >= 60) {
                // Update the session to set logout_date
                $session->update([
                    'logout_date' => $currentTime->toDateTimeString(),
                ]);

                // Log the user out and invalidate their session
               

                // Optionally, delete the user's access token
                $request->user()->currentAccessToken()->delete();

                // Respond with a session expiration message
                return response()->json(['message' => 'Session expired. You have been logged out.'], 401);
            }
        }
    }

    // Proceed with the request if the session is valid
    return $next($request); 
} 
    }

