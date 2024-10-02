<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;

class Account extends Model

    {
        use HasApiTokens, Notifiable, HasFactory;
    
        protected $fillable = [
            'name',
            'email',
            'role',
            'password',
            'org_log_id'
            
        ];
        public static function validateAccount($data)
    {
        $users = Account::pluck('name')->toArray();
       

        $validator = Validator::make($data, [
            
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:accounts,email'],
            'role' => ['required',  ],
            'password',
            'org_log_id' => ['required',],
           
        ]);

        return $validator;
    }
}

    
    
    

