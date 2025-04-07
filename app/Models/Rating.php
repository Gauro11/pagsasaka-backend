<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['account_id', 'product_id', 'rating', 'comment', 'seller_response'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}