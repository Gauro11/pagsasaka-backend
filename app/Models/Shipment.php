<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $table = 'shipment'; // Table name

    protected $fillable = [
        'name', 
        'ship_to', 
        'status',
    ];
}
