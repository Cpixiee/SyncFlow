<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Success Response
     *
     * @param mixed $data
     * @param string $message
     * @param int $httpCode
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $httpCode = 200): JsonResponse
    {
        return response()->json([
            'http_code' => $httpCode,
            'message' => $message,
            'error_id' => null,
            'data' => $data,
        ], $httpCode);
    }

    /**
     * Error Response
     *
     * @param string $message
     * @param string|null $errorId
     * @param int $httpCode
     * @param mixed $data
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Unknown error occured', ?string $errorId = null, int $httpCode = 500, $data = null): JsonResponse
    {
        return response()->json([
            'http_code' => $httpCode,
            'message' => $message,
            'error_id' => $errorId ?? $this->generateErrorId(),
            'data' => $data,
        ], $httpCode);
    }


    /**
     * Not Found Response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'Data not found'): JsonResponse
    {
        return $this->errorResponse($message, $this->generateErrorId(), 404);
    }

    /**
     * Unauthorized Response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, $this->generateErrorId(), 401);
    }

    /**
     * Forbidden Response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, $this->generateErrorId(), 403);
    }

    /**
     * Pagination Success Response
     *
     * @param mixed $docs
     * @param array $metadata
     * @param string $message
     * @param int $httpCode
     * @return JsonResponse
     */
    protected function paginationResponse($docs, array $metadata, string $message = 'Success', int $httpCode = 200): JsonResponse
    {
        return response()->json([
            'http_code' => $httpCode,
            'message' => $message,
            'error_id' => null,
            'data' => [
                'docs' => $docs,
                'metadata' => $metadata
            ]
        ], $httpCode);
    }

    /**
     * Validation Error Response with specific error tracking
     *
     * @param mixed $errors
     * @param string $message
     * @param string|null $specificErrorId
     * @return JsonResponse
     */
    protected function validationErrorResponse($errors = null, string $message = 'Request invalid', ?string $specificErrorId = null): JsonResponse
    {
        $errorId = $specificErrorId ?? 'VALIDATION_' . strtoupper(uniqid());
        return $this->errorResponse($message, $errorId, 400, $errors);
    }

    /**
     * Authentication Error Response
     *
     * @param string $reason
     * @return JsonResponse
     */
    protected function authErrorResponse(string $reason = 'invalid_credentials'): JsonResponse
    {
        $errorId = 'AUTH_' . strtoupper($reason) . '_' . strtoupper(uniqid());
        
        $message = match($reason) {
            'invalid_credentials' => 'Invalid username or password',
            'user_not_found' => 'User not found',
            'password_incorrect' => 'Current password is incorrect',
            'token_expired' => 'Token has expired',
            'token_invalid' => 'Invalid token',
            default => 'Authentication failed'
        };
        
        return $this->errorResponse($message, $errorId, 401);
    }

    /**
     * Generate unique error ID
     *
     * @return string
     */
    private function generateErrorId(): string
    {
        return 'ERR_' . strtoupper(uniqid());
    }
}
