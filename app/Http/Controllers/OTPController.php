<?php

namespace App\Http\Controllers;

use App\Constants\ApiMessages;
use App\Http\Controllers\Controller;
use App\Http\Requests\OTPRequest;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;

class OTPController extends Controller
{
    public function __construct(
        protected OTPService $oTPService
    ) {}

    public function userVerify(OTPRequest $request): JsonResponse
    {
        $data = $this->oTPService->userVerifyOTP($request->validated());
        $statusCode = $data["status"];
        unset($data['status']);
        return success(
            $data,
            ApiMessages::MSG_SUCCESS,
            statusCode: $statusCode,
        );
    }

    public function sendOTP(): JsonResponse
    {
        $user = auth()->user();
        $otp = $this->oTPService->createOTP($user->id, $user->phone_number);

        return success(
            config('app.env') == 'local' ? ['otp' => $otp->otp] : null,
            ApiMessages::MSG_SUCCESS,
        );
    }
}
