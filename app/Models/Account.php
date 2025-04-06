<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Account extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'role_id',
        'password',
        'security_id',
        'security_answer',
        'phone_number',
        'is_archived',
        'avatar',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
}