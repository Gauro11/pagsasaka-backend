<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'product_name',
        'description',
        'price',
        'stocks',
        'unit',
        'product_img',
        'visibility',
        'is_archived',
        'account_id',
    ];

    protected $casts = [
        'product_img' => 'array',
    ];

    // In App\Models\Product.php

public function account()
{
    return $this->belongsTo(Account::class, 'account_id');
}


    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id', 'id');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function averageRating()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    public function totalRatings()
    {
        return $this->ratings()->count();
    }
}