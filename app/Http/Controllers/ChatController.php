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

            // Ensure the user is authenticated and has an account_id
            if (!$user || !isset($user->account_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or account_id not found',
                ], 401);
            }

            // Fetch conversations where the user is either the account_id or role_id
            $conversations = Conversation::where('account_id', $user->account_id)
                ->orWhere('role_id', $user->role_id)
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
                ->orderBy('updated_at', 'desc')
                ->get();

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

            // Determine the receiver_id based on the conversation
            $receiver_id = $conversation->account_id == $user->account_id ? $conversation->role_id : $conversation->account_id;

            // Create the new message
            $message = Message::create([
                'conversation_id' => $id,
                'message' => $request->message,
                'sender_id' => $user->id, // Changed from $user->account_id to $user->id
                'account_id' => $receiver_id,
                'is_read' => 0,
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