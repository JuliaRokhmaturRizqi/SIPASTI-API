<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun dinonaktifkan oleh Admin.',
            ], 403);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak memiliki hak akses ke resource ini.',
            ], 403);
        }

        return $next($request);
    }
}
