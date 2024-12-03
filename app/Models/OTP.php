<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OTP extends Model
{
    use HasFactory;

    // The table associated with the model
    protected $table = 'otps';

    // The attributes that are mass assignable
    protected $fillable = ['email', 'phone_number', 'otp', 'created_at', 'expires_at'];

    // Optional: Add timestamps if you want to manage created_at and updated_at automatically
    public $timestamps = true;
}

