<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Traits\ApiResponseTrait;

class ApiAuthenticate
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Set the authenticated user for the request
            auth('api')->setUser($user);

            return $next($request);

        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Invalid token');
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Authentication failed');
        }
    }
}
