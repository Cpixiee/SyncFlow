<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\ApiResponseTrait;

class CheckRole
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Check if user has required role
            if ($user->role !== $role) {
                return $this->forbiddenResponse("Access denied. Required role: {$role}. User role: {$user->role}");
            }

            return $next($request);

        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid token');
        }
    }
}
