<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class listofuser extends Model
{
    use HasFactory;
    protected $fillable = ['name','email','organization_id','role'];

   


}
