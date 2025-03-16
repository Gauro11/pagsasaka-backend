<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Send a new message.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Create the message
        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'message' => $request->message,
            'is_read' => 0,
            'account_id' => $user->account_id,
            'sender_id' => $user->id,
        ]);

        // Update the conversation's updated_at timestamp
        $conversation = Conversation::find($request->conversation_id);
        $conversation->touch();

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message
        ], 201);
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $count = Message::where('conversation_id', $request->conversation_id)
            ->where('is_read', 0)
            ->where('sender_id', '!=', Auth::id())
            ->update(['is_read' => 1]);

        return response()->json([
            'success' => true,
            'message' => $count . ' messages marked as read'
        ]);
    }

    /**
     * Get unread message count.
     */
    public function unreadCount()
    {
        $count = Message::where('is_read', 0)
            ->where('sender_id', '!=', Auth::id())
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    /**
     * Delete a message.
     */
    public function destroy($id)
    {
        $message = Message::findOrFail($id);
        
        // Check if the user owns the message
        if ($message->sender_id != Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this message'
            ], 403);
        }
        
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully'
        ]);
    }
}
