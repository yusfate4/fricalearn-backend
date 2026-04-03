<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStudentContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
  public function handle(Request $request, Closure $next)
{
    $studentId = $request->header('X-Active-Student-Id');

    if ($studentId && $request->user() && $request->user()->role === 'parent') {
        // 🔒 SECURITY CHECK: Ensure this student actually belongs to this parent
        $isChild = $request->user()->children()->where('child_id', $studentId)->exists();
        
        if ($isChild) {
            // Attach the student ID to the request so controllers can use it easily
            $request->merge(['active_student_id' => $studentId]);
        }
    }

    return $next($request);
}
}
