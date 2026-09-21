<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EnrollmentPayment;
use App\Models\StudentProfile;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    // ── Dashboard overview ────────────────────────────────────────
    public function overview()
    {
        $now = now();

        $totalStudents   = User::where('role', 'student')->count();
        $totalParents    = User::where('role', 'parent')->count();
        $pendingPayments = EnrollmentPayment::where('status', 'pending')->count();
        $trialsExpiring  = User::where('role', 'student')
            ->where('trial_ends_at', '>=', $now)
            ->where('trial_ends_at', '<=', $now->copy()->addDays(7))
            ->where('is_premium', 0)
            ->count();
        $expiredTrials   = User::where('role', 'student')
            ->where('trial_ends_at', '<', $now)
            ->where('is_premium', 0)
            ->count();
        $premiumStudents = User::where('role', 'student')->where('is_premium', 1)->count();
        $totalXP         = (int) StudentProfile::sum('total_points');
        $avgScore        = round(DB::table('quiz_performance')->avg('score') ?? 0);
        $lessonsCompleted = DB::table('user_external_lesson_progress')
            ->where('status', 'completed')->count();

        // Recent payments (last 5)
        $recentPayments = EnrollmentPayment::with(['parent:id,name,email'])
            ->latest()->limit(5)->get(['id','child_name','amount','currency','status','created_at','parent_id']);

        // Trials expiring soon
        $expiringTrials = User::where('role', 'student')
            ->where('trial_ends_at', '>=', $now)
            ->where('trial_ends_at', '<=', $now->copy()->addDays(7))
            ->where('is_premium', 0)
            ->select('id','name','email','trial_ends_at')
            ->orderBy('trial_ends_at')
            ->limit(10)
            ->get();

        // Unread chat messages
        $unreadChats = DB::table('messages')
            ->where('is_read', false)
            ->where('sender_id', '!=', auth()->id())
            ->count();

        return response()->json([
            'stats' => [
                'total_students'    => $totalStudents,
                'total_parents'     => $totalParents,
                'pending_payments'  => $pendingPayments,
                'trials_expiring'   => $trialsExpiring,
                'expired_trials'    => $expiredTrials,
                'premium_students'  => $premiumStudents,
                'total_xp_awarded'  => $totalXP,
                'avg_quiz_score'    => $avgScore,
                'lessons_completed' => $lessonsCompleted,
                'unread_chats'      => $unreadChats,
            ],
            'recent_payments'  => $recentPayments,
            'expiring_trials'  => $expiringTrials,
        ]);
    }

    // ── Student registry ──────────────────────────────────────────
    public function students(Request $request)
    {
        $query = User::where('role', 'student')
            ->with(['studentProfile', 'parents:id,name,email'])
            ->withCount([
                'progressRecords as lessons_completed' => fn($q) => $q->where('status', 'completed')
            ]);

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($filter = $request->query('filter')) {
            if ($filter === 'trial')   $query->where('is_premium', 0)->whereNotNull('trial_ends_at')->where('trial_ends_at', '>=', now());
            if ($filter === 'premium') $query->where('is_premium', 1);
            if ($filter === 'expired') $query->where('is_premium', 0)->where(function($q) { $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', now()); });
        }

        $students = $query->orderByDesc('created_at')->paginate(25);

        return response()->json($students);
    }

    // ── Parent registry ───────────────────────────────────────────
    public function parents(Request $request)
    {
        $query = User::where('role', 'parent')
            ->with(['children' => fn($q) => $q->select('users.id','users.name','users.trial_ends_at','users.is_premium')]);

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $parents = $query->orderByDesc('created_at')->paginate(25);

        return response()->json($parents);
    }

    // ── Quiz CRUD ─────────────────────────────────────────────────
    public function questions(Request $request)
    {
        $query = Question::with('lesson:id,title');

        if ($search = $request->query('search')) {
            $query->where('question_text', 'like', "%{$search}%");
        }
        if ($lessonId = $request->query('lesson_id')) {
            $query->where('lesson_id', $lessonId);
        }

        return response()->json($query->orderByDesc('id')->paginate(20));
    }

    public function updateQuestion(Request $request, $id)
    {
        $q = Question::findOrFail($id);
        $validated = $request->validate([
            'question_text'  => 'sometimes|string',
            'option_a'       => 'sometimes|string',
            'option_b'       => 'sometimes|string',
            'option_c'       => 'sometimes|string',
            'correct_answer' => 'sometimes|in:a,b,c',
            'explanation_text' => 'nullable|string',
        ]);
        $q->update($validated);
        return response()->json(['success' => true, 'question' => $q]);
    }

    public function deleteQuestion($id)
    {
        Question::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Analytics ─────────────────────────────────────────────────
    public function analytics()
    {
        $now = now();

        // New students per month (last 6 months)
        $newStudentsMonthly = DB::table('users')
            ->where('role', 'student')
            ->where('created_at', '>=', $now->copy()->subMonths(6))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Quiz performance distribution
        $scoreDistribution = DB::table('quiz_performance')
            ->selectRaw("
                SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as excellent,
                SUM(CASE WHEN score >= 60 AND score < 80 THEN 1 ELSE 0 END) as good,
                SUM(CASE WHEN score >= 40 AND score < 60 THEN 1 ELSE 0 END) as fair,
                SUM(CASE WHEN score < 40 THEN 1 ELSE 0 END) as poor,
                COUNT(*) as total,
                ROUND(AVG(score)) as avg_score
            ")
            ->first();

        // Lesson completions per week (last 8 weeks)
        $weeklyCompletions = DB::table('user_external_lesson_progress')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $now->copy()->subWeeks(8))
            ->selectRaw("WEEK(completed_at) as week, COUNT(*) as count")
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Active students (completed at least 1 lesson in last 30 days)
        $activeStudents = DB::table('user_external_lesson_progress')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $now->copy()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        // Subscription breakdown
        $subscriptions = [
            'premium'        => User::where('role','student')->where('is_premium',1)->count(),
            'on_trial'       => User::where('role','student')->where('is_premium',0)->where('trial_ends_at','>=',$now)->count(),
            'expired'        => User::where('role','student')->where('is_premium',0)->where('trial_ends_at','<',$now)->count(),
            'no_trial'       => User::where('role','student')->whereNull('trial_ends_at')->where('is_premium',0)->count(),
        ];

        // Top students
        $topStudents = StudentProfile::with('user:id,name')
            ->orderByDesc('total_points')
            ->limit(5)
            ->get(['user_id','total_points','current_level']);

        // Revenue this month (approved payments)
        $revenueMonth = EnrollmentPayment::where('status','approved')
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->sum('amount');

        return response()->json([
            'new_students_monthly' => $newStudentsMonthly,
            'score_distribution'   => $scoreDistribution,
            'weekly_completions'   => $weeklyCompletions,
            'active_students_30d'  => $activeStudents,
            'subscriptions'        => $subscriptions,
            'top_students'         => $topStudents,
            'revenue_this_month'   => $revenueMonth,
            'total_students'       => User::where('role','student')->count(),
            'total_parents'        => User::where('role','parent')->count(),
            'total_xp'             => (int) StudentProfile::sum('total_points'),
        ]);
    }

    // ── Payments overview (pending + trial expiry) ─────────────────
    public function paymentsOverview()
    {
        $now = now();

        $pending = EnrollmentPayment::with(['parent:id,name,email'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $approved = EnrollmentPayment::with(['parent:id,name,email'])
            ->where('status', 'approved')
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->latest()
            ->get();

        // Students whose trial expires in next 14 days
        $expiringSoon = User::where('role','student')
            ->where('is_premium', 0)
            ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(14)])
            ->with(['parents:id,name,email'])
            ->orderBy('trial_ends_at')
            ->get(['id','name','email','trial_ends_at','selected_courses']);

        // Already expired
        $expired = User::where('role','student')
            ->where('is_premium', 0)
            ->where('trial_ends_at', '<', $now)
            ->whereNotNull('trial_ends_at')
            ->orderByDesc('trial_ends_at')
            ->limit(20)
            ->get(['id','name','email','trial_ends_at','selected_courses']);

        return response()->json([
            'pending_payments' => $pending,
            'approved_this_month' => $approved,
            'expiring_soon'    => $expiringSoon,
            'expired_trials'   => $expired,
            'summary' => [
                'pending_count'      => $pending->count(),
                'approved_count'     => $approved->count(),
                'expiring_count'     => $expiringSoon->count(),
                'expired_count'      => $expired->count(),
                'monthly_revenue'    => $approved->sum('amount'),
            ],
        ]);
    }

    // ── Admin reply to chat (sends email if user offline) ─────────
    public function replyToChat(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        try {
            $message = DB::table('messages')->insertGetId([
                'conversation_id' => $conversationId,
                'sender_id'       => auth()->id(),
                'message'         => $validated['message'],
                'is_read'         => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            DB::table('conversations')->where('id', $conversationId)->update(['updated_at' => now()]);

            // Find the student/parent to email
            $conv = DB::table('conversations')->find($conversationId);
            if ($conv) {
                $recipient = User::find($conv->student_id);
                if ($recipient) {
                    try {
                        Mail::html($this->buildReplyEmail($recipient->name, $validated['message']), function($m) use ($recipient) {
                            $m->to($recipient->email)
                              ->subject('New message from FricaLearn Support');
                        });
                    } catch (\Exception $e) {
                        Log::warning('Chat reply email failed: ' . $e->getMessage());
                    }
                }
            }

            return response()->json(['success' => true, 'message_id' => $message]);
        } catch (\Exception $e) {
            Log::error('Admin reply failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function buildReplyEmail(string $name, string $body): string
    {
        $e = htmlspecialchars($name);
        $b = nl2br(htmlspecialchars($body));
        return "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
          <div style='background:#2A1650;padding:24px;border-radius:16px 16px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:18px;'>FricaLearn <span style='color:#FFFF00;'>Support</span></h1>
          </div>
          <div style='background:#fff;border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 16px 16px;'>
            <p style='color:#333;'>Hi {$e},</p>
            <div style='background:#f3effa;border-left:4px solid #3F2171;padding:16px;border-radius:0 12px 12px 0;margin:16px 0;'>
              <p style='color:#333;margin:0;'>{$b}</p>
            </div>
            <p style='color:#aaa;font-size:12px;margin-top:24px;'>You can reply by logging into your parent portal.</p>
          </div>
        </div>";
    }
}
