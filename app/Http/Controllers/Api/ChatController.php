<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * 🚪 Get or Create a conversation (Universal)
     */
    public function getConversation(Request $request)
    {
        $user = $request->user();
        $adminId = 1; // Yusuf's Admin ID (The Tutor)

        // If admin is viewing a specific student, or a student is starting a chat
        $participantId = ($user->role === 'admin' || $user->is_admin == 1) 
            ? $request->query('participant_id') 
            : $user->id;

        if (!$participantId) {
            return response()->json(['message' => 'Participant ID is required.'], 400);
        }

        // Find or Create the "Room"
        $conversation = Conversation::firstOrCreate([
            'student_id' => $participantId,
            'tutor_id' => $adminId,
        ]);

        return response()->json($conversation->load([
            'messages.sender', 
            'student', 
            'tutor'
        ]));
    }

    /**
     * 📩 Send a new message
     * Supports both 'conversation_id' or 'receiver_id' to prevent 422 errors
     */
    public function sendMessage(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'receiver_id'     => 'nullable|exists:users,id', // 🚀 Fallback for simpler frontend logic
            'message'         => 'required_without_all:image,audio|string|nullable',
            'image'           => 'nullable|image|max:5120',
            'audio'           => 'nullable|file|max:10240',
        ]);

        // 1. Resolve the Conversation
        if ($request->conversation_id) {
            $conversation = Conversation::findOrFail($request->conversation_id);
        } else {
            // If no convo_id, find/create one using the receiver_id
            $conversation = Conversation::firstOrCreate([
                'student_id' => $user->role === 'admin' ? $request->receiver_id : $user->id,
                'tutor_id'   => $user->role === 'admin' ? $user->id : ($request->receiver_id ?? 1),
            ]);
        }
        
        // 2. Security Check
        if ($conversation->student_id != $user->id && $conversation->tutor_id != $user->id) {
            return response()->json(['message' => 'Unauthorized chat access'], 403);
        }

        // 3. Handle Media (Clean paths)
        $imagePath = $request->hasFile('image') 
            ? $request->file('image')->store('chat_media', 'public') 
            : null;

        $audioPath = $request->hasFile('audio') 
            ? $request->file('audio')->store('chat_audio', 'public') 
            : null;

        // 4. Save Message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $request->message ?? '',
            'image_path'      => $imagePath,
            'audio_path'      => $audioPath,
            'is_read'         => false,
        ]);

        // Bump conversation to top of list
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
                'display_name' => $convo->student->name ?? 'Student #'.$convo->student_id, 
                'last_message' => $convo->latestMessage->message ?? '📎 Media shared',
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
  /**
     * 👑 ADMIN: Messages for one student
     */
    public function getAdminMessages($id)
    {
        $messages = Message::where('conversation_id', $id)
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ This internal call now works because we fixed the signature in the last step
        $this->markAsRead($id);

        return response()->json($messages);
    }

    /**
     * ✅ Mark as Read Logic
     */
 /**
     * ✅ Mark as Read Logic
     * Works both as an API route and an internal method call.
     */
    public function markAsRead($id)
    {
        // 📩 Logic: Update all messages in this conversation where 'is_read' is false
        // We target messages NOT sent by the current user (the admin)
        $updated = Message::where('conversation_id', $id)
            ->where('is_read', false)
            ->where('sender_id', '!=', auth()->id())
            ->update(['is_read' => true]);

        // If called as an API route, return JSON. 
        // If called internally by getAdminMessages, just return the count.
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => 'success',
                'messages_updated' => $updated
            ]);
        }

        return $updated;
    }
}