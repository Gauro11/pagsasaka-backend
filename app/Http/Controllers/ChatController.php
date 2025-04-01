<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     */
    public function index()
    {
        $user = Auth::user();
        
        $conversations = Conversation::where('account_id', $user->account_id)
            ->orWhere('role_id', $user->role_id)
            ->with(['latestMessage.sender', 'latestMessage.receiver'])
            ->orderBy('updated_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if conversation already exists
        $existingConversation = Conversation::where('account_id', $request->account_id)
            ->where('role_id', $request->role_id)
            ->first();

        if ($existingConversation) {
            return response()->json([
                'success' => true,
                'message' => 'Conversation already exists',
                'data' => $existingConversation
            ]);
        }

        // Create new conversation
        $conversation = Conversation::create([
            'account_id' => $request->account_id,
            'role_id' => $request->role_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation created successfully',
            'data' => $conversation
        ], 201);
    }

    /**
     * Show a specific conversation with messages.
     */
    public function show($id)
    {
        $conversation = Conversation::with([
            'messages' => function ($query) {
                $query->orderBy('created_at', 'asc')
                    ->with(['sender', 'receiver']); // Include sender & receiver
            }
        ])->findOrFail($id);

        // Mark all unread messages as read
        Message::where('conversation_id', $id)
            ->where('is_read', 0)
            ->where('sender_id', '!=', Auth::id())
            ->update(['is_read' => 1]);

        return response()->json([
            'success' => true,
            'data' => $conversation
        ]);
    }

    /**
     * Delete a conversation and its messages.
     */
    public function destroy($id)
    {
        $conversation = Conversation::findOrFail($id);
        
        // Delete all messages in the conversation
        Message::where('conversation_id', $id)->delete();
        
        // Delete the conversation
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully'
        ]);
    }
}
