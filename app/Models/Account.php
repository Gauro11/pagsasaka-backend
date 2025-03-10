<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
use App\Models\OrganizationalLog;
use Illuminate\Foundation\Auth\User as Authenticatable;


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
            
        ];
    public function role()
{
    return $this->belongsTo(Role::class, 'role_id', 'id');
}

}

    
    
    

