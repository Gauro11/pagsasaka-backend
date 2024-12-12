<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'product_id',
        'quantity',
        'total_amount',
        'created_at',
        'updated_at',
        'ship_to',
        'status',
    ];

// In Order model
public function product()
{
    return $this->belongsTo(Product::class, 'product_id', 'id');
}


    
}

