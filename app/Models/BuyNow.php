<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyNow extends Model
{
    use HasFactory;

    // Specify the table if it is not following Laravel's default plural naming convention
    protected $table = 'buy_now';

    // Specify the fillable fields
    protected $fillable = [
        'account_id',
        'product_id',
        'quantity',
        'unit',
        'price',
        'item_total',
    ];

    // Define the relationship with the Product model
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Define the relationship with the Account (User) model
    public function account()
    {
        return $this->belongsTo(Account::class);  // Assuming you have an Account model for user info
    }
}

