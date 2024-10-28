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
            'Firstname',
            'Lastname',
            'Middlename',
            'email',
            'role',
            'password',
            'status',
            'org_log_id'
            
        ];
        public static function validateAccount($data)
    {
        $users = Account::pluck('Firstname')->toArray();
       

        $validator = Validator::make($data, [
            
            'Firstname' => ['required', 'string','min:3','max:225'],
            'Lastname' => ['required', 'string','min:3','max:225'],
            'Middlename' => ['required', 'string','min:1','max:225'],
            'email' => ['required', 'email', 'unique:accounts,email'],
            'role' => ['required'  ],
            'password',
            'org_log_id' => ['required',],
           
        ]);

        return $validator;
    }
}

    
    
    

