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
                'must_change_password' => $user->mustChangePassword(),
                'password_changed_at' => $user->password_changed_at ? $user->password_changed_at->format('Y-m-d H:i:s') : null,
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

    /**
     * Create a new user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:login_users,username',
            'password' => 'nullable|string|min:6', // Optional, akan default ke admin#1234
            'role' => 'required|in:operator,admin,superadmin',
            'photo_url' => 'nullable|string|url',
            'employee_id' => 'required|string|unique:login_users,employee_id',
            'phone' => 'required|string',
            'email' => 'required|email|unique:login_users,email',
            'position' => 'required|in:manager,staff,supervisor',
            'department' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse(
                $validator->errors(),
                'Request invalid'
            );
        }

        try {
            // Use default password if not provided or use provided password
            $password = $request->filled('password') ? $request->password : 'admin#1234';
            
            // Create new user
            $user = LoginUser::create([
                'username' => $request->username,
                'password' => Hash::make($password),
                'role' => $request->role,
                'photo_url' => $request->photo_url,
                'employee_id' => $request->employee_id,
                'phone' => $request->phone,
                'email' => $request->email,
                'position' => $request->position,
                'department' => $request->department,
                'password_changed' => false, // Default belum pernah ganti password
                'password_changed_at' => null,
            ]);

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
                'must_change_password' => $user->mustChangePassword(),
                'default_password' => $password === 'admin#1234' ? 'admin#1234' : null,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse(
                $userData,
                'User created successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create user',
                'USER_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Change user password (first time or regular change).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
            'new_password_confirmation' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse(
                $validator->errors(),
                'Request invalid'
            );
        }

        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->validationErrorResponse(
                    ['current_password' => ['Current password is incorrect']],
                    'Current password is incorrect'
                );
            }

            // Check if new password is different from current
            if (Hash::check($request->new_password, $user->password)) {
                return $this->validationErrorResponse(
                    ['new_password' => ['New password must be different from current password']],
                    'New password must be different from current password'
                );
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            // Mark password as changed (for first time change tracking)
            $user->markPasswordChanged();

            // Prepare response data
            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'must_change_password' => false, // Now false after change
                'password_changed_at' => $user->password_changed_at->format('Y-m-d H:i:s'),
                'message' => 'Password changed successfully'
            ];

            return $this->successResponse(
                $userData,
                'Password changed successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not change password',
                'PASSWORD_CHANGE_ERROR',
                500
            );
        }
    }
}
