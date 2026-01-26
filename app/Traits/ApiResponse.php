<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function error($message = null, $errors = null, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    protected function unauthorized($message = 'Non autorisé')
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    protected function forbidden($message = 'Accès interdit')
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }
}