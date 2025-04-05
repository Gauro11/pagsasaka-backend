<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    protected $table = 'chat_sessions';

    protected $fillable = ['user1_id', 'user2_id'];

    public function messages()
    {
        return $this->hasMany(Messages::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(Messages::class, 'conversation_id')->latestOfMany();
    }

    public function user1()
    {
        return $this->belongsTo(Account::class, 'user1_id'); // Uses existing Account model
    }

    public function user2()
    {
        return $this->belongsTo(Account::class, 'user2_id'); // Uses existing Account model
    }

    public function unreadMessagesCount($userId)
    {
        return $this->messages()
            ->where('receiver_id', $userId)
            ->where('is_read', 0)
            ->count();
    }
}