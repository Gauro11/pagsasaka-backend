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
        'ship_to',
        'quantity',
        'total_amount',
        'status',
        'delivery_proof',
        'created_at',
        'updated_at'
    ];

    // Define relationship with Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
