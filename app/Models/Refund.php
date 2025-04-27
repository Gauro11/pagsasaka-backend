<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'product_id',
        'order_id',
        'reason',
        'solution',
        'refund_amount',
        'return_method',
        'payment_method',
        'product_refund_img',
    ];
}
