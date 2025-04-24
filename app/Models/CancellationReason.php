<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationReason extends Model
{
    use HasFactory;

    // Make sure the reasons field is fillable
    protected $fillable = ['reasons'];

    // Optional: define table name explicitly if you want
    // protected $table = 'cancellation_reasons';
}

