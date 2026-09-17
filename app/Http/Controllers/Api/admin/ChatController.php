<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Cloudinary\Cloudinary;

class ChatController extends Controller
{
    /**
     * 🚪 Get or Create a conversation (Universal)
     */
    public function getConversation(Request $request)
    {
        try {
            $user = $request->user();
            $adminId = 1; 
            $participantId = $request->query('participant_id') ?? $user->id;

            $conversation = Conversation::firstOrCreate([
                'student_id' => $participantId,
                'tutor_id'   => $adminId,
            ]);

            // Mark as read automatically when entering
            $this->markAsRead($conversation->id);

            return response()->json($conversation->load([
                'messages.sender', 
                'student', 
                'tutor'
            ]));

        } catch (\Exception $e) {
            Log::error("Chat Sync Crash: " . $e->getMessage());
            return response()->json([
                'message' => 'Server Error',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📩 Send a new message
     */
    public function sendMessage(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'receiver_id'     => 'nullable|exists:users,id', 
            'message'         => 'required_without_all:image,audio|string|nullable',
            'image'           => 'nullable|image|max:5120',
            'audio'           => 'nullable|file|max:10240',
        ]);

        if ($request->conversation_id) {
            $conversation = Conversation::find($request->conversation_id);
        } else {
            $targetUserId = ($user->role === 'admin' || $user->is_admin == 1) ? $request->receiver_id : $user->id;
            $conversation = Conversation::firstOrCreate([
                'student_id' => $targetUserId,
                'tutor_id'   => 1,
            ]);
        }
        
        $imagePath = null; $audioPath = null;

        if (class_exists('Cloudinary\Cloudinary')) {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
            ]);

            try {
                if ($request->hasFile('image')) {
                    $upload = $cloudinary->uploadApi()->upload($request->file('image')->getRealPath(), ['folder' => 'fricalearn/chat/images']);
                    $imagePath = $upload['secure_url'];
                }
                if ($request->hasFile('audio')) {
                    $upload = $cloudinary->uploadApi()->upload($request->file('audio')->getRealPath(), ['folder' => 'fricalearn/chat/audio', 'resource_type' => 'video']);
                    $audioPath = $upload['secure_url'];
                }
            } catch (\Exception $e) {
                Log::error("Media Upload Error: " . $e->getMessage());
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'receiver_id'     => ($user->role === 'admin' || $user->is_admin == 1) ? $conversation->student_id : 1,
            'message'         => $request->message ?? '',
            'image_path'      => $imagePath,
            'audio_path'      => $audioPath,
            'is_read'         => false,
        ]);

        $conversation->touch();
        return response()->json($message->load('sender'), 201);
    }

    /**
     * 👑 ADMIN: Master list of all chats
     */
    public function getAdminConversations()
    {
        $conversations = Conversation::with(['student', 'latestMessage'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $formatted = $conversations->map(function($convo) {
            return [
                'id'           => $convo->id,
                'display_name' => $convo->student->name ?? 'User #'.$convo->student_id, 
                'last_message' => $convo->latestMessage->message ?? '📎 Attachment',
                'updated_at'   => $convo->updated_at->diffForHumans(),
                'unread_count' => $convo->messages()->where('is_read', false)->where('sender_id', '!=', auth()->id())->count(),
                'student_id'   => $convo->student_id
            ];
        });

        return response()->json($formatted);
    }

    /**
     * 👑 ADMIN: Messages for one student
     */
    public function getAdminMessages($id)
    {
        $messages = Message::where('conversation_id', $id)
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        $this->markAsRead($id);

        return response()->json($messages);
    }

    /**
     * ✅ THE FIX: Robust Mark as Read logic
     */
    public function markAsRead($id)
    {
        try {
            $updated = Message::where('conversation_id', $id)
                ->where('is_read', false)
                ->where('sender_id', '!=', auth()->id())
                ->update(['is_read' => true]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'messages_updated' => $updated
                ]);
            }
            return $updated;
        } catch (\Exception $e) {
            Log::error("MarkAsRead Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}