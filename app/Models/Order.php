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
        'cancellation_reason',
        'refund_reason',
        'delivery_proof',
        'created_at',
        'updated_at',
        'payment_method',
        'order_number',
    ];

    

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    // Define relationship with Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function cancellationReason()
{
    return $this->belongsTo(CancellationReason::class, 'cancellation_reason_id');
}
}
