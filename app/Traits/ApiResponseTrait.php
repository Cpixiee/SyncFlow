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
            'errorId' => null,
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
    protected function errorResponse(string $message = 'Unknown error occurred', string $errorId = null, int $httpCode = 500, $data = null): JsonResponse
    {
        return response()->json([
            'http_code' => $httpCode,
            'message' => $message,
            'errorId' => $errorId ?? $this->generateErrorId(),
            'data' => $data,
        ], $httpCode);
    }

    /**
     * Validation Error Response
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    protected function validationErrorResponse($errors = null, string $message = 'Request invalid'): JsonResponse
    {
        return $this->errorResponse($message, $this->generateErrorId(), 400, $errors);
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
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
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
     * Generate unique error ID
     *
     * @return string
     */
    private function generateErrorId(): string
    {
        return 'ERR_' . strtoupper(uniqid());
    }
}
