<?php

namespace App\Services;

use App\Constants\ApiMessages;
use App\Constants\ExceptionMessages;
use App\Exceptions\ApiException;
use App\Http\Resources\Users\Profile\UserSugResource;
use App\Jobs\SendSMSOTPJob;
use App\Models\OTP;
use App\Models\User;
use App\Models\Users\Profile\UserDevice;
use App\Models\Users\Profile\UserProfile;
use App\Services\MainService;
use App\Services\Users\Profile\LoginHistoryService;
use App\Services\Users\Auth\AuthService;
use App\Traits\UniqueKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\RateLimiter;
use Stevebauman\Location\Facades\Location;

class OTPService extends MainService
{
    use UniqueKey;

    public function __construct(
        protected LoginHistoryService $loginHistoryService,
    ) {}

    public function createOTP($user_id, $phone_number, $min = 110000, $max = 990000)
    {
        //Check if the exists otp have been created/updated from at least (_custom.time_to_new_OTP)
        if ($lastOTP = OTP::where("user_id", $user_id)->first()) {
            $now = Carbon::parse(Carbon::now()->format("Y-m-d H:i:s"));
            $past = Carbon::parse($lastOTP->updated_at)->addSeconds(config("_custom.time_to_new_OTP"));
            if ($now->lt($past))
                throw new ApiException(null, trans(ApiMessages::MSG_WAIT_TIME_TO_NEW_OTP), 400);
        }

        $this->OTPAttempts(false);
        //Create new otp
        $OTP = $this->generateUniqeNumericKey(OTP::class,  "otp", min: $min, max: $max);
        $otp = OTP::updateOrCreate(
            ['user_id' => $user_id],
            [
                'otp' => $OTP,
                'updated_at' => Carbon::now()->format("Y-m-d H:i:s"),
                "expire_at" => Carbon::now()->addMinutes(config("_custom.otp_expire_in")),
            ],
        );

        WhatsAppSendOTP($phone_number, $otp->otp);
        
        //TODO dispatch Queue
        SendSMSOTPJob::dispatch($phone_number, $otp);

        return $otp;
    }

    public function userVerifyOTP($validatedData)
    {

        /**
         * @var \App\Models\User $user
         */
        $user = auth()->user();
        $ip = Location::get(request()->ip());

        if (
            $this->OTPAttempts() && ($otp = OTP::query()
                ->where(["otp" => $validatedData["otp"], "user_id" => $user->id])
                ->where("expire_at", ">", Carbon::now()->format("Y-m-d H:i:s"))
                ->first())
        ) {
            $otp->delete(); // Delete the otp because it's used

            $user->deactive_at = null; //Activate user account

            $user->name == null ?
                $status = config('_custom.complete_profile_SC') //I'm new to the app
                : $status = 200; // I'm logging in

            if (!$user->account_verified_at) { // OTP of register
                //Verify the account
                $user->account_verified_at = Carbon::now();

                //Create profile
                UserProfile::create([
                    "user_id" => $user->id,
                ]);

                $status = config('_custom.complete_profile_SC'); //Then I'm registring
            }

            $user->save();

            //Create User Device
            if ($validatedData["notification_token"] || $validatedData["device_id"]) {
                $this->updateDeviceInfo($user, $validatedData , $user->role_id);
            }

            //Create a loginHistory
            $loginHistory = $this->loginHistoryService->store(
                $user->id,
                $validatedData["device_name"],
                $ip,
                $validatedData['device_id'],
            );

            //invalidate the token
            JWTAuth::invalidate(JWTAuth::getToken());

            $data["tokens"] = app(AuthService::class)->generateTokens($user, $loginHistory->id);
            $data["status"] = $status;
            $data["user"]   = UserSugResource::make($user);
            if ($user->role_id != 3 && $user->role_id != 4)
                $data["abilities"] = $user->role->abilities()->pluck('ability_id')->toArray();

            return $data;
        }
        throw new ApiException(null, trans(ApiMessages::MSG_INVALID_OTP_CODE), 400);
    }

    public function updateDeviceInfo($user, $validatedData , $role_id = null)
    {
        // dd($role_id);
        $existingDevice = UserDevice::whereHas("user", function ($query) use ($role_id) {
                $query->where('role_id', $role_id);
            })
            ->where('device_id', $validatedData['device_id'] ?? null)
            // ->orWhere('notification_token', $validatedData["notification_token"])
            ->first();

            // dd($validatedData);
        // dd($existingDevice , $role_id);
        if ($existingDevice) {
            // Update the existing record
            $existingDevice->update([
                'notification_token' => $validatedData["notification_token"],
                'device_id' => $validatedData["device_id"] ?? null,
                'user_id' => $user->id,
            ]);
        } else {
            // Create a new record

            // dd($validatedData);
            UserDevice::create([
                'notification_token' => $validatedData["notification_token"],
                'device_id' => $validatedData["device_id"] ?? null,
                'user_id' => $user->id,
            ]);
        }
    }

    public function OTPAttempts($hit = true)
    {
        $key = "otp_attempts:user-" . auth()->id();
        if (RateLimiter::tooManyAttempts($key, config("_custom.otp_max_attempts"))) {
            throw new ApiException(null, trans(ExceptionMessages::MSG_TOO_MANY_ATTEMPTS, ["decay_minutes" => round(config("_custom.otp_decay_minutes") / 60, 1)]), 400);
        }
        $hit ? RateLimiter::hit($key, config("_custom.otp_decay_minutes")) : false;
        return true;
    }
}
