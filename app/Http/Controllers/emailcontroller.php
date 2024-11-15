<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\LogsAPICalls;
use App\Models\ApiLog; 
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache; // Use cache for simple tracking
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{

    public function sendEmail(Request $request)
    {
        // Validate the email address
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->input('email');

        $firstDueDate = Carbon::now()->addDays(30)->format('F j, Y');
        $secondDueDate = Carbon::now()->addDays(15)->format('F j, Y');
        $finalDueDate = Carbon::now()->addDays(7)->format('F j, Y');
        $superFinalDueDate = Carbon::now()->addDays(1)->format('F j, Y');

        // Use cache to track how many times an email has been sent
        $emailKey = 'email_sent_count_' . $email;
        $sendCount = Cache::get($emailKey, 0);

        // Log the send count for the email
        Log::info("Send count for {$email}: {$sendCount}");

        // Increase the send count by 1
        $sendCount += 1;
        Cache::put($emailKey, $sendCount, now()->addDays(30));

        try {
            if ($sendCount === 1) {
                // First notification
                $htmlContent = "
                <html>
                <body>
                    <p>Good Day!</p>
                    <p>A gentle reminder to comply with the submission of the required documents tagged to your good office on the Data Bank Management System.</p>
                    <p>Please do submit the needed files on or before <strong>{$firstDueDate}</strong>.</p>
                    <p>We do appreciate your effort in participating in the initiatives of the Research, Extension, and Innovation Office in modernizing and improving the process of data transmitting and managing.</p>
                    <p><a href='https://dmo-server-dev.bulsutech.com/'>Click here</a> to access the Data Bank Management System.</p>
                    <p>Thank you!</p>
                </body>
                </html>
            ";
                $subject = 'First Notification for Data Bank Management System';
            } elseif ($sendCount === 2) {
                // Second notification
                $htmlContent = "
                <html>
                <body>
                    <p>Good Day!</p>
                    <p>This is a second reminder to submit the required documents for the Data Bank Management System.</p>
                    <p>Please ensure submission of the files by <strong>{$secondDueDate}</strong>.</p>
                    <p>We do appreciate your effort in participating in the initiatives of the Research, Extension, and Innovation Office in modernizing and improving the process of data transmitting and managing.</p>
                    <p><a href='https://dmo-server-dev.bulsutech.com/'>Click here</a> to access the Data Bank Management System.</p>
                    <p>Thank you!</p>
                </body>
                </html>
            ";
                $subject = 'Second Notification for Data Bank Management System';
            } elseif ($sendCount === 3) {
                // Final notification
                $htmlContent = "
                <html>
                <body>
                    <p>Good Day!</p>
                    <p>This is the final reminder to submit the required documents for the Data Bank Management System.</p>
                    <p>Please ensure submission of the files by <strong>{$finalDueDate}</strong>.</p>
                    <p>We do appreciate your effort in participating in the initiatives of the Research, Extension, and Innovation Office in modernizing and improving the process of data transmitting and managing.</p>
                    <p><a href='https://dmo-server-dev.bulsutech.com/'>Click here</a> to access the Data Bank Management System.</p>
                    <p>Thank you!</p>
                </body>
                </html>
            ";
                $subject = 'Final Notification for Data Bank Management System';
            } else {
                // Super final notification
                $htmlContent = "
                <html>
                <body>
                    <p>Good Day!</p>
                    <p>This is the super final reminder to submit the required documents for the Data Bank Management System.</p>
                    <p>Please ensure submission of the files by <strong>{$superFinalDueDate}</strong>.</p>
                    <p>We do appreciate your effort in participating in the initiatives of the Research, Extension, and Innovation Office in modernizing and improving the process of data transmitting and managing.</p>
                    <p><a href='https://dmo-server-dev.bulsutech.com/'>Click here</a> to access the Data Bank Management System.</p>
                    <p>Thank you!</p>
                </body>
                </html>
            ";
                $subject = 'Super Final Notification for Data Bank Management System';
            }

            // Send the email with the generated HTML content
            Mail::send([], [], function ($message) use ($email, $htmlContent, $subject) {
                $message->to($email)
                    ->subject($subject)
                    ->setBody($htmlContent, 'text/html');
            });

            // API response data
            $resp = ['status' => 'success', 'message' => ucfirst($subject) . ' email sent successfully'];

            $this->logAPICalls('sendEmail', null, $request->all(), $resp);
            return response()->json($resp, 200);
        } catch (Throwable $e) {
            $resp = ['status' => 'error', 'message' => $e->getMessage()];

            $this->logAPICalls('sendEmail', null, $request->all(), $resp);
            return response()->json($resp, 500);
        }
    }

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
   