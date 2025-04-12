<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'name', 'address', 'is_default', 'is_billing'];

    // Define the relationship with the Account model
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}