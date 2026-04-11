<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Allows Super Admins and Tutors into the administrative backend.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthorized. Please login.'], 401);
        }

        /**
         * 2. Permission Logic:
         * We allow access if:
         * - is_admin is true (Super Admin/Founder)
         * - OR role is 'admin'
         * - OR role is 'tutor'
         */
        if ($user->is_admin == 1 || $user->role === 'admin' || $user->role === 'tutor') {
            return $next($request);
        }

        // 3. Block everyone else (Students/Parents)
        return response()->json([
            'message' => 'Access denied. Staff-level credentials required.',
            'status'  => 'forbidden'
        ], 403);
    }
}