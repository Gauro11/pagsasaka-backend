<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'message',
        'is_read',
        'account_id',
        'sender_id',
    ];

    /**
     * Get the conversation that owns the message.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of the message.
     */
    public function sender()
    {
        return $this->belongsTo(Account::class, 'sender_id')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'avatar']);
    }

    /**
     * Get the receiver of the message.
     */
    public function receiver()
    {
        return $this->belongsTo(Account::class, 'account_id')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'avatar']);
    }
}