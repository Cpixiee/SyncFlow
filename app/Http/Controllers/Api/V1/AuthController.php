<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoginUser;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Middleware will be handled in routes
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse(
                $validator->errors(),
                'Request invalid'
            );
        }

        $credentials = $request->only('username', 'password');

        try {
            // Attempt to verify the credentials and create a token for the user
            $user = LoginUser::where('username', $credentials['username'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->unauthorizedResponse('Invalid credentials');
            }

            // Create token
            $token = JWTAuth::fromUser($user);

            // Prepare user data for response (excluding password)
            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'photo_url' => $user->photo_url,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'email' => $user->email,
                'position' => $user->position,
                'department' => $user->department,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ];

            return $this->successResponse(
                $userData,
                'Login successful'
            );

        } catch (JWTException $e) {
            return $this->errorResponse(
                'Could not create token',
                'JWT_ERROR',
                500
            );
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'photo_url' => $user->photo_url,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'email' => $user->email,
                'position' => $user->position,
                'department' => $user->department,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse($userData);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve user data',
                'USER_DATA_ERROR',
                500
            );
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return $this->successResponse(
                null,
                'Successfully logged out'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not logout user',
                'LOGOUT_ERROR',
                500
            );
        }
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());

            return $this->successResponse([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not refresh token',
                'TOKEN_REFRESH_ERROR',
                500
            );
        }
    }
}
