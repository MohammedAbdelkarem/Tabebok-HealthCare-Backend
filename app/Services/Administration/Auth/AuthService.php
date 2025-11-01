<?php

namespace App\Services\Administration\Auth;

use App\Constants\ExceptionMessages;
use App\Exceptions\ApiException;
use App\Http\Resources\Administration\Profile\AdminListResource;
use App\Models\JWTPersonalTokens;
use App\Models\User;
use App\Models\Users\Profile\LoginHistory;
use Carbon\Carbon;
use App\Models\Users\Profile\UserDevice;
use App\Services\JWTTokensService;
use App\Services\OTPService;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\MainService;
use App\Services\Users\Profile\LoginHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Stevebauman\Location\Facades\Location;

class AuthService extends MainService
{
    public function __construct(
        protected OTPService $OTPService,
        protected JWTTokensService $jwtService,
        protected LoginHistoryService $loginHistoryService,
    ) {}

    public function login($validatedData)
    {

        $users = User::all();
        foreach ($users as $user) {
            $user->phone_number = deconstructPhoneNumber($user->phone_number);
            $user->save();
        }

        // //Create the account
        $user = User::query()
            ->where("email", $validatedData["email"])
            ->with('role')
            ->first();

        //Validate Data
        if (
            $this->loginAttempts(true, $user->id) && (Hash::check($validatedData["password"], $user->adminProfile->password))
        ) {
            //Check if the user is logging in using the admin api
            if ($user->role_id == 3 || $user->deactive_at)
                throw new ApiException(null, trans(ExceptionMessages::MSG_ACCEESS_DENIED), 400);

            //Create a loginHistory
            $loginHistory = $this->loginHistoryService->store(
                $user->id,
                $validatedData["device_name"],
                Location::get(request()->ip())
            );

            //Generate Token
            $token = $this->generateTokens($user, $loginHistory->id);

            $this->OTPService->updateDeviceInfo($user, $validatedData);

            $data = [
                "tokens"    => $token,
                "user"      => AdminListResource::make($user),
                "abilities" => $user->role->abilities()->pluck('ability_id')->toArray()
            ];
            return $data;
        }
        throw new ApiException(null, trans(ExceptionMessages::MSG_INVALID_CREDENTIALS), 400);
    }

    public function activeSessions()
    {
        return LoginHistory::where('user_id', auth()->id())
            ->whereHas('token', function ($q) {
                $q->where('expire_at', '>', Carbon::now());
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function logoutSessions($ids)
    {
        $this->jwtService->invalidateSessionByDevice($ids);
    }

    public function logout($notiToken)
    {
        /**
         * @var \App\Models\User $user
         */
        $user = auth()->user();

        $this->jwtService->InvalidateTokenWithRelated(JWTAuth::getToken());

        if ($notiToken)
            $user->userDevices()->where('notification_token', $notiToken)->delete();
    }

    public function logoutAllDevices()
    {
        $user = auth()->user();

        $this->jwtService->InvalidateAllTokensByUserID($user->id);
        UserDevice::where('user_id', $user->id)->delete();
    }

    public function refresh()
    {
        $loginHistoryId = JWTPersonalTokens::where('token', JWTAuth::getToken())
            ->first()?->access_token()->first()?->login_history;

        $this->jwtService->InvalidateTokenWithRelated(JWTAuth::getToken());
        $data['tokens'] = $this->generateTokens(auth()->user(), $loginHistoryId);
        return $data;
    }

    public function generateTokens($user, $loginHistoryId)
    {
        $accessExpireIn = Carbon::now()->addMinutes(config('jwt.ttl'))->timestamp;
        $refreshExpireIn = Carbon::now()->addMinutes(config('jwt.refresh_ttl'))->timestamp;

        $accessToken  = JWTAuth::customClaims([
            'exp'               => $accessExpireIn,
            'api_access'        => true,
            'refresh_access'    => false,
        ])->fromUser($user);
        $refreshToken = JWTAuth::customClaims([
            'exp'               => $refreshExpireIn,
            'api_access'        => false,
            'refresh_access'    => true,
        ])->fromUser($user);

        //Store Tokens In DB
        $accessTokenDB  = $this->jwtService->store($accessToken, null, $loginHistoryId);
        $this->jwtService->store($refreshToken, $accessTokenDB->id);

        return [
            "access_token"      => $accessToken,
            "refresh_token"     => $refreshToken,
            "access_expire_in"  => $accessExpireIn,
            "refresh_expire_in" => $refreshExpireIn,
        ];
    }

    public function loginAttempts($hit = true, $user_id)
    {
        $key = "login_attempts:user-$user_id";
        if (RateLimiter::tooManyAttempts($key, config("_custom.max_login_attempts"))) {
            throw new ApiException(null, trans(ExceptionMessages::MSG_TOO_MANY_ATTEMPTS, ["decay_minutes" => round(config("_custom.decay_login_minutes") / 60, 1)]), 400);
        }
        $hit ? RateLimiter::hit($key, config("_custom.decay_login_minutes")) : false;
        return true;
    }
}
