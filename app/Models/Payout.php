<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'amount',
        'scheduled_date',
        'time_slot',
        'queue_number',
        'status',
        'created_at',
        'updated_at',
    ];
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}