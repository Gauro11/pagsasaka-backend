<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Messages;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessagesController extends Controller
{
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'message' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $userId = Auth::id();

    if (!$userId) {
        return response()->json([
            'success' => false,
            'message' => 'User not authenticated',
        ], 401);
    }

    $user = Account::find($userId);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found in accounts table',
        ], 404);
    }

    // Assuming the user has an active conversation. You could get it in a different way.
    $chatSession = ChatSession::where('user1_id', $user->id)->orWhere('user2_id', $user->id)->first();

    if (!$chatSession) {
        return response()->json([
            'success' => false,
            'message' => 'No active chat session found for the user.',
        ], 404);
    }

    // Get the other participant (receiver) in the chat session.
    $otherParticipantId = ($chatSession->user1_id == $user->id) ? $chatSession->user2_id : $chatSession->user1_id;

    // Assuming you want to send the message to the other participant
    $message = Messages::create([
        'conversation_id' => $chatSession->id,
        'sender_id' => $user->id,
        'receiver_id' => $otherParticipantId,
        'message' => $request->message,
        'is_read' => 0,
    ]);

    $chatSession->touch(); // Update the chat session's timestamp.

    return response()->json([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $message
    ], 201);
}


    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:chat_sessions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $user = Account::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in accounts table',
            ], 404);
        }

        $chatSession = ChatSession::findOrFail($request->conversation_id);

        $authorized = ($chatSession->user1_id == $user->id) || ($chatSession->user2_id == $user->id);
        if (!$authorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to mark messages as read in this chat session',
            ], 403);
        }

        Messages::where('conversation_id', $request->conversation_id)
                ->where('receiver_id', $user->id)
                ->where('is_read', 0)
                ->update(['is_read' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read successfully',
        ], 200);
    }

    public function unreadCount()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $user = Account::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in accounts table',
            ], 404);
        }

        $unreadCount = Messages::where('receiver_id', $user->id)
                               ->where('is_read', 0)
                               ->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread message count retrieved successfully',
            'data' => [
                'unread_count' => $unreadCount
            ]
        ], 200);
    }

    public function destroy($id)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $user = Account::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in accounts table',
            ], 404);
        }

        $message = Messages::findOrFail($id);

        if ($message->sender_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this message',
            ], 403);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ], 200);
    }
}
