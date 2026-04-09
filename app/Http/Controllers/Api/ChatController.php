<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
// 🚀 Use the pure Cloudinary SDK for manual injection
use Cloudinary\Cloudinary;

class ChatController extends Controller
{
    /**
     * 🚪 Get or Create a conversation (Universal)
     */
   public function getConversation(Request $request)
{
    $user = $request->user();
    $adminId = 1; 

    // Determine who the other person is
    $participantId = ($user->role === 'admin' || $user->is_admin == 1) 
        ? $request->query('participant_id') 
        : $user->id;

    if (!$participantId) {
        return response()->json(['message' => 'Participant ID is required.'], 400);
    }

    // 1. Find or create the conversation "Room"
    $conversation = Conversation::firstOrCreate([
        'student_id' => $participantId,
        'tutor_id'   => $adminId,
    ]);

    // 🚀 THE CRITICAL FIX: 
    // Sometimes the admin sends messages via receiver_id but doesn't attach the convo_id.
    // We update any orphaned messages to belong to this conversation.
    Message::where('conversation_id', null)
        ->where(function($q) use ($participantId, $adminId) {
            $q->where([['sender_id', $participantId], ['receiver_id', $adminId]])
              ->orWhere([['sender_id', $adminId], ['receiver_id', $participantId]]);
        })->update(['conversation_id' => $conversation->id]);

    // 2. Mark incoming messages as read for the current user
    $this->markAsRead($conversation->id);

    // 3. Return the conversation with all messages
    return response()->json($conversation->load([
        'messages.sender', 
        'student', 
        'tutor'
    ]));
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

        // 1. Resolve the Conversation
        if ($request->conversation_id) {
            $conversation = Conversation::findOrFail($request->conversation_id);
        } else {
            // If no convo_id, find/create one using the receiver_id
            $conversation = Conversation::firstOrCreate([
                'student_id' => ($user->role === 'admin' || $user->is_admin == 1) ? $request->receiver_id : $user->id,
                'tutor_id'   => ($user->role === 'admin' || $user->is_admin == 1) ? $user->id : ($request->receiver_id ?? 1),
            ]);
        }
        
        // 2. 🛡️ THE FIX: Admin/Tutor Bypass
        // Allow access if the user is a participant OR is an Admin
        $isAdmin = ($user->role === 'admin' || $user->is_admin == 1);
        $isParticipant = ($conversation->student_id == $user->id || $conversation->tutor_id == $user->id);

        if (!$isParticipant && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized chat access'], 403);
        }

        // 3. ☁️ Handle Media with Cloudinary Manual SDK
        $imagePath = null;
        $audioPath = null;

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        try {
            if ($request->hasFile('image')) {
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('image')->getRealPath(),
                    ['folder' => 'fricalearn/chat/images']
                );
                $imagePath = $upload['secure_url'];
            }

            if ($request->hasFile('audio')) {
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('audio')->getRealPath(),
                    [
                        'folder' => 'fricalearn/chat/audio',
                        'resource_type' => 'video' // Cloudinary requires 'video' for audio files
                    ]
                );
                $audioPath = $upload['secure_url'];
            }

            // 4. Save Message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'message'         => $request->message ?? '',
                'image_path'      => $imagePath, // Now storing Cloudinary URL
                'audio_path'      => $audioPath, // Now storing Cloudinary URL
                'is_read'         => false,
            ]);

            $conversation->touch();

            return response()->json($message->load('sender'), 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed', 'details' => $e->getMessage()], 500);
        }
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
     * ✅ Mark as Read Logic
     */
    public function markAsRead($id)
    {
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
    }
}