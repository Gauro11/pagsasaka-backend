<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Messages;
use App\Models\Account; // Ensure this import is present
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatSessionController extends Controller
{
    public function show($id)
{
    try {
        // Validate the id parameter
        if (!is_numeric($id) || $id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid chat session ID provided',
            ], 400);
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

        $chatSession = ChatSession::findOrFail($id);

        $authorized = ($chatSession->user1_id == $user->id) || ($chatSession->user2_id == $user->id);
        if (!$authorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this chat session',
            ], 403);
        }

        $chatSession->load(['messages' => function ($query) {
            $query->with(['sender', 'receiver'])->orderBy('created_at', 'asc');
        }]);

        // Mark messages as read for the authenticated user
        $chatSession->messages->where('receiver_id', $user->id)->where('is_read', 0)->each(function ($message) {
            $message->update(['is_read' => 1]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Chat session retrieved successfully',
            'data' => $chatSession
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Chat session not found',
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve chat session: ' . $e->getMessage(),
        ], 500);
    }
}

    public function index(Request $request)
{
    try {
        // Get the authenticated user's ID
        $currentUserId = auth()->id();

        if (!$currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Fetch chat sessions where the authenticated user is either user1 or user2
        $chatSessions = ChatSession::where('user1_id', $currentUserId)
            ->orWhere('user2_id', $currentUserId)
            ->with([
                'latestMessage.sender' => function ($query) {
                    $query->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                },
                'latestMessage.receiver' => function ($query) {
                    $query->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                },
                'user1' => function ($query) {
                    $query->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                },
                'user2' => function ($query) {
                    $query->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                }
            ])
            ->get();

        // Transform the response to include the chat partner's name and avatar
        $chatSessions = $chatSessions->map(function ($chatSession) use ($currentUserId) {
            // Determine the chat partner (the user who is NOT the current user)
            $chatPartner = ($chatSession->user1_id == $currentUserId) ? $chatSession->user2 : $chatSession->user1;

            // Construct the chat partner's full name
            $chatPartnerName = $chatPartner
                ? trim("{$chatPartner->first_name} {$chatPartner->middle_name} {$chatPartner->last_name}")
                : null;

            // Add chat partner's name and avatar to the response
            $chatSession->chat_partner_name = $chatPartnerName;
            $chatSession->chat_partner_avatar = $chatPartner && $chatPartner->avatar ? $chatPartner->avatar : null;

            // Optionally include unread messages count
            $chatSession->unread_messages_count = $chatSession->unreadMessagesCount($currentUserId);

            // Unset user1 and user2 to clean up the response (optional)
            unset($chatSession->user1);
            unset($chatSession->user2);

            return $chatSession;
        });

        return response()->json([
            'success' => true,
            'message' => 'Chat sessions retrieved successfully',
            'data' => $chatSessions
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve chat sessions: ' . $e->getMessage(),
        ], 500);
    }
}

public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user2_id' => 'required|exists:accounts,id|not_in:' . Auth::id(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
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

        $existingSession = ChatSession::where(function ($query) use ($user, $request) {
            $query->where('user1_id', $user->id)
                  ->where('user2_id', $request->user2_id);
        })->orWhere(function ($query) use ($user, $request) {
            $query->where('user1_id', $request->user2_id)
                  ->where('user2_id', $user->id);
        })->first();

        if ($existingSession) {
            $chatPartner = Account::find($existingSession->user1_id == $user->id ? $existingSession->user2_id : $existingSession->user1_id);
            return response()->json([
                'success' => true,
                'message' => 'Chat session already exists',
                'data' => [
                    'id' => $existingSession->id,
                    'chat_partner_name' => $chatPartner->first_name . ' ' . $chatPartner->last_name,
                    'chat_partner_avatar' => $chatPartner->avatar,
                ]
            ], 200);
        }

        $chatSession = ChatSession::create([
            'user1_id' => $user->id,
            'user2_id' => $request->user2_id,
        ]);

        $chatPartner = Account::find($request->user2_id);

        return response()->json([
            'success' => true,
            'message' => 'Chat session created successfully',
            'data' => [
                'id' => $chatSession->id,
                'chat_partner_name' => $chatPartner->first_name . ' ' . $chatPartner->last_name,
                'chat_partner_avatar' => $chatPartner->avatar,
            ]
        ], 201);
    }

    public function destroy($id)
{
    try {
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

        $chatSession = ChatSession::findOrFail($id);

        $authorized = ($chatSession->user1_id == $user->id) || ($chatSession->user2_id == $user->id);

        if (!$authorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this chat session',
            ], 403);
        }

        // Delete all messages associated with the chat session
        Messages::where('conversation_id', $id)->delete();

        // Delete the chat session
        $chatSession->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chat session deleted successfully',
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete chat session: ' . $e->getMessage(),
        ], 500);
    }
}

}