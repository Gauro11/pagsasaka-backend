<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    // Specify the columns that are fillable
    protected $fillable = [
        'session_code',
        'user_id',
        'login_date',
        'logout_date',
        'status',
    ];

    // Optional: Explicitly specify the table name
    protected $table = 'sessions';

    // Optionally handle timestamps
    public $timestamps = true;
}


