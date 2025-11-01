<?php

use GuzzleHttp\Client;
use App\Constants\ApiMessages;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;

if(!function_exists('WhatsappSendOTP'))
{
    function WhatsAppSendOTP($phone_number, $otp)
    {
        $phone_number = constructPhoneNumber($phone_number);

        $client = new Client();

        $url = 'https://msgpilot.net/whatsapp/api/v1/message/text/send';

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . config('services.whatsapp.token'),
        ];

        $body = [
            'session_id' => config('services.whatsapp.session_id'),
            'receiver'   => $phone_number,
            'text'       => __(ApiMessages::WHATSAPP_OTP, ['otp' => $otp]),
        ];

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json'    => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                Log::warning("WhatsApp OTP response (non-200):", [
                    'status' => $statusCode,
                    'body' => $responseBody,
                ]);
            }
        } catch (RequestException $e) {
            Log::error('Failed to send WhatsApp OTP via Guzzle', [
                'phone' => $phone_number,
                'otp' => $otp,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
        }
    }
}
if (!function_exists('constructPhoneNumber')) {
    function constructPhoneNumber($phone_number)
    {
        // Replace leading '0' with '+963'
        return '+963' . substr($phone_number, 1);
    }
}
