<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Get or Create a conversation (Used by both Student and Admin)
     */
    public function getConversation(Request $request)
    {
        $user = $request->user();
        $adminId = 1; // Yusuf's Admin ID

        // Logic: If Admin, they MUST send ?student_id=X. 
        // If Student, we ignore the query and use their own ID.
        $studentId = ($user->is_admin == 1) ? $request->query('student_id') : $user->id;

        if (!$studentId) {
            return response()->json(['message' => 'Student identification failed.'], 400);
        }

        // Find or create the unique chat for this student-tutor pair
        $conversation = Conversation::firstOrCreate([
            'student_id' => $studentId,
            'tutor_id' => $adminId,
        ]);

        // Load ALL messages without the limit(1) restriction
        return response()->json($conversation->load([
            'messages.sender', 
            'student', 
            'tutor'
        ]));
    }

    /**
     * Send a new message
     */


    public function sendMessage(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'audio' => 'nullable|file|max:10240', // 👈 Accept audio files up to 10MB
        ]);

        // Require at least text OR an image OR an audio note
        if (!$request->message && !$request->hasFile('image') && !$request->hasFile('audio')) {
            return response()->json(['error' => 'Cannot send an empty message'], 422);
        }

        $conversation = Conversation::find($request->conversation_id);
        
        if ($conversation->student_id != $user->id && $conversation->tutor_id != $user->id) {
            return response()->json(['message' => 'Unauthorized chat access'], 403);
        }

        // Handle Image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat_media', 'public'); 
        }

        // 👈 Handle Audio
        $audioPath = null;
        if ($request->hasFile('audio')) {
            // Save inside storage/app/public/chat_audio
            $audioPath = $request->file('audio')->store('chat_audio', 'public'); 
        }

        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'sender_id' => $user->id,
            'message' => $request->message ?? '',
            'image_path' => $imagePath,
            'audio_path' => $audioPath,
        ]);

        $conversation->touch();

        return response()->json($message->load('sender'));
    }
    /**
     * ADMIN: Get all conversations for the master list sidebar
     */
    public function getAllConversations()
    {
        // Fetch all chats including the student's name and all messages
        // Removed the ->limit(1) so the Admin sees the full history
        $conversations = Conversation::with(['student', 'messages'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($conversations);
    }

    /**
     * Mark all messages in a conversation as read (Admin only)
     */
    public function markAsRead($id)
    {
        Message::where('conversation_id', $id)
            ->where('sender_id', '!=', auth()->id()) // Don't mark your own messages
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Conversation marked as read']);
    }
}