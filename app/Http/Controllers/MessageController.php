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
     * Send a new message in a conversation.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request, $id)
{
    try {
        $user = Auth::user();

        // Ensure the user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Fetch the conversation
        $conversation = Conversation::findOrFail($id);

        // Check if the user is authorized to send a message in this conversation
        if ($conversation->account_id != $user->account_id && $conversation->role_id != $user->role_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to send a message in this conversation',
            ], 403);
        }

        // Fetch the last message in the conversation to get the sender's account_id
        $lastMessage = Message::where('conversation_id', $id)
            ->latest()
            ->first();

        // Get the sender's account_id to reply to them
        $receiver_id = $lastMessage->sender_id == $user->id 
            ? $conversation->account_id 
            : $conversation->role_id;

        // Create the new message as a reply
        $message = Message::create([
            'conversation_id' => $id,
            'message' => $request->message,
            'sender_id' => $user->id, // Sender is the authenticated user
            'account_id' => $receiver_id, // The receiver_id (based on the sender of the last message)
            'is_read' => 0, // Initially unread
        ]);

        // Load the sender and receiver relationships for the new message
        $message->load([
            'sender' => function ($q) {
                $q->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
            },
            'receiver' => function ($q) {
                $q->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
            }
        ]);

        // Update the conversation's updated_at timestamp
        $conversation->touch();

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message
        ], 201);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send message: ' . $e->getMessage(),
        ], 500);
    }
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
