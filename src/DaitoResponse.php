<?php

namespace Daito\Lib;

use RuntimeException;

class DaitoResponse
{
    /**
     * Build a successful response payload.
     */
    public static function success($message = 'Success', array $arrData = array(), $statusCode = 200)
    {
        return self::make(true, $message, $arrData, $statusCode);
    }

    /**
     * Build a failed response payload.
     */
    public static function fail($message = 'Failed', array $arrData = array(), $statusCode = 400)
    {
        return self::make(false, $message, $arrData, $statusCode);
    }

    /**
     * Build a response payload with a stable structure for all APIs.
     */
    public static function make($isSuccess, $message, array $arrData = array(), $statusCode = 200)
    {
        return array_merge(array(
            'success' => $isSuccess ? 1 : 0,
            'message' => (string) $message,
            'status_code' => (int) $statusCode,
        ), $arrData);
    }

    /**
     * Convert payload array to JSON.
     */
    public static function toJson(array $arrPayload)
    {
        $json = json_encode($arrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Can not encode response payload to JSON.');
        }

        return $json;
    }

    /**
     * Create success payload and return JSON string.
     */
    public static function successJson($message = 'Success', array $arrData = array(), $statusCode = 200)
    {
        return self::toJson(self::success($message, $arrData, $statusCode));
    }

    /**
     * Create failed payload and return JSON string.
     */
    public static function failJson($message = 'Failed', array $arrData = array(), $statusCode = 400)
    {
        return self::toJson(self::fail($message, $arrData, $statusCode));
    }

    /**
     * Build a validation failed payload with HTTP 422 default.
     */
    public static function validateFail(array $arrErrors, $message = 'Validation failed', $statusCode = 422)
    {
        return self::fail(
            $message,
            array(
                'errors' => $arrErrors,
            ),
            $statusCode
        );
    }

    /**
     * Build a validation failed payload and return JSON string.
     */
    public static function validateFailJson(array $arrErrors, $message = 'Validation failed', $statusCode = 422)
    {
        return self::toJson(self::validateFail($arrErrors, $message, $statusCode));
    }
}
