<?php

namespace App\Services\Users\Auth;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\User;
use App\Services\OTPService;
use App\Services\MainService;
use App\Exceptions\ApiException;
use App\Models\JWTPersonalTokens;
use App\Constants\MediaCollection;
use App\Services\JWTTokensService;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Constants\ExceptionMessages;
use App\Enums\PublishStatusEnum;
use App\Models\NotificationManagement;
use App\Services\Doctor\DoctorService;
use App\Models\Users\Profile\UserDevice;
use App\Models\Users\Profile\ArchivedUser;
use App\Models\Users\Profile\LoginHistory;
use App\Services\Base\ContextService;
use App\Services\Plan\PlanService;

/**
 * Class AuthService.
 */
class AuthService extends MainService

{
    public function __construct(
        protected OTPService $OTPService,
        protected JWTTokensService $jwtService,
        protected DoctorService $doctorService,
        protected ContextService $contextService,
    ) {}

    public function registerForDoctor($validatedData)
    {
        //Check for phone number if used to create to many accounts
        if (ArchivedUser::where('phone_number', $validatedData["phone_number"])->count() >= config("_custom.max_accounts_per_phone_number"))
            throw new ApiException(null, trans(ExceptionMessages::MSG_PHONE_NUMBER_USED_MANY_TIMES), 400);
        //check if the plan is published

        $plan = Plan::find($validatedData["plan_id"]);

        $this->contextService->checkIfPlanIsPublished($plan);

        if(!$validatedData['has_been_paid'])
            return unprocessableFailure([] , ExceptionMessages::MSG_CAN_NOT_REGISTER_WITHOUT_PAYMENT);

        $user = User::create([
            "role_id" => 3, //doctor role
            'city_id' => $validatedData['city_id'],
            "name" => $validatedData["name"],
            "phone_number" => $validatedData["phone_number"],
            "email" => $validatedData["email"],
            "birth_date" => $validatedData["birth_date"],
            "is_male" => $validatedData["is_male"],
            "language" => config("app.locale"),  
        ]);

        //Store Image
        if (isset($validatedData["avatar"])) {
            $user->avatar = $this->storeFile(
                file: $validatedData["avatar"],
                path: "users/{$user->id}"
            );
        }

        $user->save();

        $validatedData['user_id'] = $user->id;
        //create the doctor info
        $this->doctorService->storeRegisteredDoctor($validatedData);
        //Send otp
        $otp = $this->OTPService->createOTP($user->id, $validatedData['phone_number']);

        //Generate Token
        $token = $this->generateLoginToken($user);

        $data = [
            "otp"    => (string) $otp->otp, //TODO Check for remove
            // "otp"    => config("app.env") == "local" ? (string) $otp->otp : "", //TODO Check for remove
            "tokens" => $token,
            "user"   => [
                "id" => $user->id,
                "user_phone_number" => $user->phone_number,
            ],
        ];

        return $data;
    }
    public function loginForPatient($validatedData)
    {
        $user = User::firstOrCreate(
            [
                'phone_number' => $validatedData['phone_number'],
                'role_id' => 4
                ],
            [
                'phone_number' => $validatedData['phone_number'],
                'role_id' => 4,
                'language' => config("app.locale"),
                ]
            );
        if (ArchivedUser::where('phone_number', $validatedData["phone_number"])->count() >= config("_custom.max_accounts_per_phone_number"))
            throw new ApiException(null, trans(ExceptionMessages::MSG_PHONE_NUMBER_USED_MANY_TIMES), 400);

        $user->save();

        NotificationManagement::firstOrCreate([
            'user_id' => $user->id
        ],
        [
            'user_id' => $user->id
        ]);

        //Send otp
        $otp = $this->OTPService->createOTP($user->id, $validatedData['phone_number']);

        //Generate Token
        $token = $this->generateLoginToken($user);

        $data = [
             "otp"    => (string) $otp->otp, //TODO Check for remove
            // "otp"    => config("app.env") == "local" ? (string) $otp->otp : "", //TODO Check for remove
            "tokens" => $token,
            "user"   => [
                "id" => $user->id,
                "user_phone_number" => $user->phone_number,
            ],
        ];

        return $data;
    }
    public function loginForDoctor($validatedData)
    {

        $user = User::where('phone_number' , $validatedData['phone_number'])
                        ->where('role_id' , 3)->first();
        
        //Send otp
        $otp = $this->OTPService->createOTP($user->id, $validatedData['phone_number']);

        //Generate Token
        $token = $this->generateLoginToken($user);

        $data = [
             "otp"    => (string) $otp->otp, //TODO Check for remove
            // "otp"    => config("app.env") == "local" ? (string) $otp->otp : "", //TODO Check for remove
            "tokens" => $token,
            "user"   => [
                "id" => $user->id,
                "user_phone_number" => $user->phone_number,
            ],
        ];

        return $data;
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

    /**
     * Generate Token for otp request only
     * No need to store the token into DB because it's only for otp verification
     */
    public function generateLoginToken($user)
    {
        $accessExpireIn = Carbon::now()->addMinutes(config('jwt.otp_ttl'))->timestamp;

        $accessToken  = JWTAuth::customClaims([
            'exp'               => $accessExpireIn,
            'otp_access'        => true,
            'api_access'        => false,
            'refresh_access'    => false,
        ])->fromUser($user);

        return [
            "access_token"      => $accessToken,
            "access_expire_in"  => $accessExpireIn,
        ];
    }
}
