<?php

namespace App\Http\Controllers\Traits;

trait ApiResponds
{
    /**
     * Standard dataset success response matching useDatasetActions.js.
     */
    protected function respondSuccess(string $message, $record = null)
    {
        $response = [
            'result' => true,
            'msg'    => $message,
        ];

        if ($record !== null) {
            $response['record'] = $record;
        }

        return response()->json($response, 200);
    }

    /**
     * Standard dataset failure response matching useDatasetActions.js.
     */
    protected function respondError(string $message, int $code = 200)
    {
        return response()->json([
            'result' => false,
            'msg'    => $message,
        ], $code);
    }

    /**
     * Legacy success response method.
     */
    public function sendResponse($result, $message)
    {
        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ], 200);
    }

    /**
     * Legacy error response method.
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
