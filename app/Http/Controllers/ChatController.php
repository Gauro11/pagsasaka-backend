<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
{
    try {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        if (!isset($user->account_id)) {
            return response()->json([
                'success' => false,
                'message' => 'account_id not found for user ID: ' . $user->id,
            ], 401);
        }

        $conversations = Conversation::where('account_id', $user->account_id)
            ->orWhere('role_id', $user->role_id)
            ->with('latestMessage.sender', 'latestMessage.receiver')
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($conversations->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No conversations found',
                'data' => []
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversations retrieved successfully',
            'data' => $conversations
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve conversations: ' . $e->getMessage(),
        ], 500);
    }
}


    /**
     * Create a new conversation.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
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
                'account_id' => 'required|exists:accounts,id',
                'role_id' => 'required|exists:roles,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
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
                ], 200);
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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific conversation with messages.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
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

            // Fetch the conversation with messages
            $conversation = Conversation::with([
                'messages' => function ($query) {
                    $query->orderBy('created_at', 'asc')
                        ->with([
                            'sender' => function ($q) {
                                $q->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                            },
                            'receiver' => function ($q) {
                                $q->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                            }
                        ]);
                }
            ])->findOrFail($id);

            // Check if the user is authorized to view this conversation
            if ($conversation->account_id != $user->account_id && $conversation->role_id != $user->role_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this conversation',
                ], 403);
            }

            // Mark all unread messages as read (for messages not sent by the user)
            Message::where('conversation_id', $id)
                ->where('is_read', 0)
                ->where('sender_id', '!=', $user->id)
                ->update(['is_read' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation retrieved successfully',
                'data' => $conversation
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Delete a conversation and its messages.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
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

            // Fetch the conversation
            $conversation = Conversation::findOrFail($id);

            // Check if the user is authorized to delete this conversation
            if ($conversation->account_id != $user->account_id && $conversation->role_id != $user->role_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this conversation',
                ], 403);
            }

            // Delete all messages in the conversation
            Message::where('conversation_id', $id)->delete();

            // Delete the conversation
            $conversation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted successfully',
                'data' => null
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation: ' . $e->getMessage(),
            ], 500);
        }
    }
}