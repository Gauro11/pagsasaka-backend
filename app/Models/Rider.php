<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Rider extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $table = 'riders'; // Ensure correct table

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone_number',
        'license',
        'valid_id',
        'status',
        'avatar',
        'role_id'
    ];

    protected $appends = ['role_id'];

    // Always return role_id as 4
    public function getRoleIdAttribute()
    {
        return 4;
    }

    

    // Relationship: A rider has many orders
    public function orders()
    {
        return $this->hasMany(Order::class, 'rider_id', 'id');
    }
}

