<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;

class ChatController extends Controller
{
    /**
     * Get or Create a conversation
     */
    public function getConversation(Request $request)
    {
        try {
            $user          = $request->user();
            $adminId       = 1;
            $participantId = $request->query('participant_id') ?? $user->id;

            $conversation = Conversation::firstOrCreate([
                'student_id' => $participantId,
                'tutor_id'   => $adminId,
            ]);

            $this->markAsRead($conversation->id);

            return response()->json($conversation->load([
                'messages.sender',
                'student',
                'tutor',
            ]));

        } catch (\Exception $e) {
            Log::error('Chat Sync Crash: ' . $e->getMessage());
            return response()->json([
                'message'      => 'Server Error',
                'error_detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a new message — and email the admin if the sender is a parent/student
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
            $targetUserId = ($user->role === 'admin' || $user->is_admin == 1)
                ? $request->receiver_id
                : $user->id;

            $conversation = Conversation::firstOrCreate([
                'student_id' => $targetUserId,
                'tutor_id'   => 1,
            ]);
        }

        $imagePath = null;
        $audioPath = null;

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
                    $upload    = $cloudinary->uploadApi()->upload(
                        $request->file('image')->getRealPath(),
                        ['folder' => 'fricalearn/chat/images']
                    );
                    $imagePath = $upload['secure_url'];
                }
                if ($request->hasFile('audio')) {
                    $upload    = $cloudinary->uploadApi()->upload(
                        $request->file('audio')->getRealPath(),
                        ['folder' => 'fricalearn/chat/audio', 'resource_type' => 'video']
                    );
                    $audioPath = $upload['secure_url'];
                }
            } catch (\Exception $e) {
                Log::error('Media Upload Error: ' . $e->getMessage());
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'receiver_id'     => ($user->role === 'admin' || $user->is_admin == 1)
                ? $conversation->student_id
                : 1,
            'message'         => $request->message ?? '',
            'image_path'      => $imagePath,
            'audio_path'      => $audioPath,
            'is_read'         => false,
        ]);

        $conversation->touch();

        // ── Email admin when a parent / student sends a message ──────
        // Only fires for non-admin senders so admin replies don't loop
        if (!($user->role === 'admin' || $user->is_admin == 1)) {
            try {
                $adminEmails = User::where('role', 'admin')
                    ->orWhere('is_admin', 1)
                    ->pluck('email')
                    ->toArray();

                if (!empty($adminEmails)) {
                    $senderName = $user->name ?? 'A parent';
                    $msgText    = $request->input('message') ?? '(image or voice note)';

                    $html  = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">';
                    $html .= '<div style="background:#2A1650;padding:24px;border-radius:16px 16px 0 0;">';
                    $html .= '<h1 style="color:#fff;margin:0;font-size:18px;">FricaLearn <span style="color:#FFFF00;">Support</span></h1>';
                    $html .= '</div>';
                    $html .= '<div style="background:#fff;border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 16px 16px;">';
                    $html .= '<p style="color:#333;font-size:15px;">New support message from <strong>' . htmlspecialchars($senderName) . '</strong>:</p>';
                    $html .= '<div style="background:#f3effa;border-left:4px solid #3F2171;padding:16px;border-radius:0 12px 12px 0;margin:16px 0;">';
                    $html .= '<p style="color:#333;margin:0;font-size:14px;">' . nl2br(htmlspecialchars($msgText)) . '</p>';
                    $html .= '</div>';
                    $html .= '<a href="https://fricalearn.com/admin/chats" style="background:#3F2171;color:#fff;padding:13px 28px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;">Open Support Chat</a>';
                    $html .= '<p style="color:#aaa;font-size:11px;margin-top:20px;">Replying from the admin dashboard will also email the parent automatically.</p>';
                    $html .= '</div></div>';

                    Mail::html($html, function ($m) use ($adminEmails, $senderName) {
                        $m->to($adminEmails)
                          ->subject('New Support Message from ' . $senderName . ' - FricaLearn');
                    });
                }
            } catch (\Exception $e) {
                Log::warning('Admin chat notification failed: ' . $e->getMessage());
            }
        }
        // ─────────────────────────────────────────────────────────────

        return response()->json($message->load('sender'), 201);
    }

    /**
     * ADMIN: Master list of all conversations
     */
    public function getAdminConversations()
    {
        $conversations = Conversation::with(['student', 'latestMessage'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $formatted = $conversations->map(function ($convo) {
            return [
                'id'           => $convo->id,
                'display_name' => $convo->student->name ?? 'User #' . $convo->student_id,
                'last_message' => $convo->latestMessage->message ?? '(Attachment)',
                'updated_at'   => $convo->updated_at->diffForHumans(),
                'unread_count' => $convo->messages()
                    ->where('is_read', false)
                    ->where('sender_id', '!=', auth()->id())
                    ->count(),
                'student_id'   => $convo->student_id,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * ADMIN: All messages for one conversation
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
     * Mark messages as read
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
                    'status'           => 'success',
                    'messages_updated' => $updated,
                ]);
            }

            return $updated;

        } catch (\Exception $e) {
            Log::error('MarkAsRead Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
