<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders'; // Ensure it's using the correct table

    protected $fillable = [
        'account_id',
        'product_id',
        'quantity',
        'total_amount',
        'ship_to',
        'status',
        'delivery_proof',
    ];
}
