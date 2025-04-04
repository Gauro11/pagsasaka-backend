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
            // Get the authenticated user's ID (from the token)
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Fetch the user from the accounts table
            $user = Account::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in accounts table',
                ], 404);
            }

            $chatSession = ChatSession::with([
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

            $authorized = ($chatSession->user1_id == $user->id) || ($chatSession->user2_id == $user->id);

            if (!$authorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this chat session',
                ], 403);
            }

            Messages::where('conversation_id', $id)
                   ->where('is_read', 0)
                   ->where('sender_id', '!=', $user->id)
                   ->update(['is_read' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Chat session retrieved successfully',
                'data' => $chatSession
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve chat session: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index()
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

            $chatSessions = ChatSession::where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id)
                ->with([
                    'latestMessage' => function ($query) {
                        $query->with([
                            'sender' => function ($q) {
                                $q->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                            },
                            'receiver' => function ($q) {
                                $q->select('id', 'first_name', 'middle_name', 'last_name', 'avatar');
                            }
                        ]);
                    }
                ])
                ->get()
                ->map(function ($chatSession) use ($user) {
                    $otherParticipant = ($chatSession->user1_id == $user->id)
                        ? $chatSession->user2
                        : $chatSession->user1;

                    return [
                        'id' => $chatSession->id,
                        'created_at' => $chatSession->created_at,
                        'updated_at' => $chatSession->updated_at,
                        'latest_message' => $chatSession->latestMessage,
                        'other_participant' => $otherParticipant
                            ? $otherParticipant->only(['id', 'first_name', 'middle_name', 'last_name', 'avatar'])
                            : null,
                        'unread_count' => $chatSession->unreadMessagesCount($user->id),
                    ];
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
            return response()->json([
                'success' => true,
                'message' => 'Chat session already exists',
                'data' => $existingSession
            ], 200);
        }

        $chatSession = ChatSession::create([
            'user1_id' => $user->id,
            'user2_id' => $request->user2_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Chat session created successfully',
            'data' => $chatSession
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