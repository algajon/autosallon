<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // not logged in -> go to login
        if (!$user) {
            return redirect()->route('login');
        }

        // must be admin
        if (! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            abort(403, 'Only admins can access this area.');
        }

        return $next($request);
    }
}
