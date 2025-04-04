<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Messages extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_id', 'receiver_id', 'message', 'is_read'];

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(Account::class, 'sender_id'); // Uses existing Account model
    }

    public function receiver()
    {
        return $this->belongsTo(Account::class, 'receiver_id'); // Uses existing Account model
    }
}