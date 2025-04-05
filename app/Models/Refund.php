<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'consumer_id', 'reason', 'status',
    ];

    // Define the relationship with the Order model
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Define the relationship with the Consumer (User) model
    public function consumer()
    {
        return $this->belongsTo(User::class, 'consumer_id');
    }
}

