<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'role_id',
    ];

    /**
     * Get all messages for this conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the account associated with the conversation.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the role associated with the conversation.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }
}
