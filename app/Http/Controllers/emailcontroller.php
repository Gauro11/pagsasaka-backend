<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\LogsAPICalls;
use App\Models\ApiLog; // Make sure to import the ApiLog model
use Throwable;

class emailcontroller extends Controller
{
    public function sendEmail(Request $request)
    {
        // Validate the email address
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        try {
            // Dynamically configure mail settings
            Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');
            Config::set('mail.mailers.smtp.port', 587);
            Config::set('mail.mailers.smtp.username', 'milbertgaringa5@gmail.com');
            Config::set('mail.mailers.smtp.password', 'xebhmzupnhpbcbpx');
            Config::set('mail.mailers.smtp.encryption', 'tls');
            Config::set('mail.from.address', 'milbertgaringa5@gmail.com');
            Config::set('mail.from.name', 'DMO');

            // Send a simple email
            Mail::raw('Good Day! A gentle reminder to comply for the submission of documents tagged to your good office of the Data Bank Management System. Please do submit the needed files', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Welcome to Our Application');
            });

            // API response data
            $resp = ['status' => 'success', 'message' => 'Email sent successfully'];

            // Log API call with success status
            $this->logAPICalls('sendEmail', null, $request->all(), $resp);

            return response()->json($resp, 200);
        } catch (Throwable $e) {
            // API error response data
            $resp = ['status' => 'error', 'message' => $e->getMessage()];

            // Log API call with error status
            $this->logAPICalls('sendEmail', null, $request->all(), $resp);

            return response()->json($resp, 500);
        }
    }

    /**
     * Log API calls into ApiLog table
     */
    public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
    {
        try {
            // If userId is null, use a default value for logging
            $userId = $userId ?? 'N/A'; // Or any default placeholder you'd prefer

            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            // Handle logging error silently
            return false; // Indicate failure
        }
        return true; // Indicate success
    }
}
