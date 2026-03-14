<?php

namespace Daito\Lib\Traits;

use Daito\Lib\DaitoResponse;

trait HasDaitoResponse
{
    protected function successResponse($message = 'Success', array $arrData = array(), $statusCode = 200)
    {
        return DaitoResponse::success($message, $arrData, $statusCode);
    }

    protected function failResponse($message = 'Failed', array $arrData = array(), $statusCode = 400)
    {
        return DaitoResponse::fail($message, $arrData, $statusCode);
    }

    protected function validateFailResponse(array $arrErrors, $message = 'Validation failed', $statusCode = 422)
    {
        return DaitoResponse::validateFail($arrErrors, $message, $statusCode);
    }

    protected function successResponseJson($message = 'Success', array $arrData = array(), $statusCode = 200)
    {
        return DaitoResponse::successJson($message, $arrData, $statusCode);
    }

    protected function failResponseJson($message = 'Failed', array $arrData = array(), $statusCode = 400)
    {
        return DaitoResponse::failJson($message, $arrData, $statusCode);
    }

    protected function validateFailResponseJson(array $arrErrors, $message = 'Validation failed', $statusCode = 422)
    {
        return DaitoResponse::validateFailJson($arrErrors, $message, $statusCode);
    }

    protected function jsonOK(array $arrData = array(), $statusCode = 200, array $arrHeaders = array())
    {
        $arrPayload = $this->successResponse('', $arrData, $statusCode);

        return response()->json($arrPayload, $arrPayload['status_code'], $arrHeaders);
    }

    protected function jsonSuccess($message = 'Success', array $arrData = array(), $statusCode = 200, array $arrHeaders = array())
    {
        $arrPayload = $this->successResponse($message, $arrData, $statusCode);

        return response()->json($arrPayload, $arrPayload['status_code'], $arrHeaders);
    }

    protected function jsonFail($message = 'Failed', array $arrData = array(), $statusCode = 400, array $arrHeaders = array())
    {
        $arrPayload = $this->failResponse($message, $arrData, $statusCode);

        return response()->json($arrPayload, $arrPayload['status_code'], $arrHeaders);
    }

    protected function jsonValidateFail(array $arrErrors, $message = 'Validation failed', $statusCode = 422, array $arrHeaders = array())
    {
        $arrPayload = $this->validateFailResponse($arrErrors, $message, $statusCode);

        return response()->json($arrPayload, $arrPayload['status_code'], $arrHeaders);
    }
}
