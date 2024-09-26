<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function organizationalLog()
    {
        return $this->belongsTo(OrganizationalLog::class, 'program_entity_id', 'id');
    }
    // public function organizationalLog()
    // {
    //     return $this->belongsTo(OrganizationalLog::class, 'program_entity_id'); // relationship sa OrganizationalLog
    // }
    
    // public function collegeLog()
    // {
    //     return $this->belongsTo(OrganizationalLog::class, 'college_entity_id'); // relationship sa OrganizationalLog
    // }
}
