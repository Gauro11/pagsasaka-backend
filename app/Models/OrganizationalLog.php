<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Program;

class OrganizationalLog extends Model
{
    use HasFactory;
    protected $guarded = [];

     // Relationship for Program
     public function programs()
     {
        return $this->hasMany(Program::class, 'program_entity_id', 'id');
     }
 
}
