<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
{
    // If the user is logged in AND their 'is_admin' column is 1
    if (auth()->check() && auth()->user()->is_admin == 1) {
        return $next($request);
    }

    // Otherwise, block the request
    return response()->json(['message' => 'Founder access only!'], 403);
}
}