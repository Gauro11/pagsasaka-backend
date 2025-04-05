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

    // In Product.php
public function orders()
{
    return $this->hasMany(Order::class, 'product_id', 'id'); // Adjust foreign key if needed
}

// A product has many ratings
public function ratings()
{
    return $this->hasMany(Rating::class);
}

// Calculate the average rating for the product
public function averageRating()
{
    return $this->ratings()->avg('rating') ?? 0;
}

// Get the total number of ratings
public function totalRatings()
{
    return $this->ratings()->count();
}
    
}

