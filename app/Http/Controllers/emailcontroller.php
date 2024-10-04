<?php

namespace App\Http\Controllers;

use App\Notifications\PHPMailerService;
use Illuminate\Http\Request;

/*class emailcontroller extends Controller
{
    protected $mailService;

    public function __construct(PHPMailerService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function sendEmail(Request $request)
    {
        $to = $request->input('email');
        $subject = "Welcome to Our App";
        $body = "<h1>Welcome</h1><p>This is a test email sent using PHPMailer in Laravel.</p>";

        $response = $this->mailService->sendEmail($to, $subject, $body);

        return response()->json(['message' => $response]);
    }
}

