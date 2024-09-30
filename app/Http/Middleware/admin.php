<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  mixed  ...$allowedUserTypes
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$allowedUserTypes)
    {
        $user = Auth::user();

        // Check if the user is logged in
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Access denied. You must be logged in to perform this action.',
            ], 401); // Unauthorized
        }

        // Check if the user's type is in the allowed list
        if (in_array($user->role, $allowedUserTypes)) {
            return $next($request);
        }

        return response()->json([
            'isSuccess' => false,
            'message' => 'Access denied. You do not have permission to perform this action.',
        ], 403); // Forbidden
    }
}
