<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CODOrder extends Model
{
    use HasFactory;

    protected $table = 'cod_orders';

    protected $fillable = [
        'account_id',
        'product_id',
        'quantity',
        'total_amount',
        'ship_to',
        'status',
    ];
}
