<?php

use App\Constants\RouteNames;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\ComplaintController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Media\MediaController;
use App\Http\Controllers\Media\StoryController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Media\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\System\Info\FAQController;
use App\Http\Controllers\System\Info\TosController;
use App\Http\Controllers\System\Info\CityController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\SubCategoryController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\System\Info\AboutUsController;
use App\Http\Controllers\System\SystemSettingController;
use App\Http\Controllers\System\Info\ContactUsController;
use App\Http\Controllers\System\Info\FaqCategoryController;
use App\Http\Controllers\Administration\AdminHomeController;
use App\Http\Controllers\Administration\Auth\AuthController;
use App\Http\Controllers\Administration\Log\BanLogController;
use App\Http\Controllers\System\Info\PrivacyPolicyController;
use App\Http\Controllers\System\Notification\NotificationController;
use App\Http\Controllers\Administration\Profile\UserProfileController;
use App\Http\Controllers\Administration\Profile\AdminProfileController;
use App\Http\Controllers\System\CustomerServiceCard\CustomerServiceCardController;



/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Admins API routes for admins in the system
|
*/

// No Auth Needed
Route::middleware([])->group(function () {
    Route::controller(AuthController::class)->middleware('bots')->group(function () {
        Route::post("/login", "login")->name('login');
    });
    Route::prefix('doctors')->controller(DoctorController::class)->group(function(){
        Route::get('get' , 'getAll');
        Route::get('profile/{id}' , 'profile')->name(RouteNames::DOCTORS_GET_PROFILE);
        // Route::delete('rate/delete/{id}' , 'deleteRate');
    });
});

//Auth Needed
Route::group(['middleware' => ['auth:api', "is_admin", 'token.access_api', 'user.active', 'user.verified']], function () {

    // Auth
    Route::controller(AuthController::class)->group(function () {
        Route::get("/active-session", "activeSessions");
        Route::post("/logout-session", "logoutSessions");
        Route::post("/logout", "logout");
        Route::get("/logout-all", "logoutAll");
        Route::get("/refresh", "refresh")->withoutMiddleware('token.access_api')->withoutMiddleware('token.access_refresh');
    });

    // //Home
    Route::controller(AdminHomeController::class)->group(function () {
        Route::get("/home", "home");
        Route::get("/overview", "overview");
    });

    //Profiles
    //Admins
    Route::prefix('profile')->controller(AdminProfileController::class)->group(function () {
        //Profile Settings
        Route::get("/login-history/{id?}", "loginHistory")->name(RouteNames::LOGIN_HISTORY_List);
        Route::post("/lang", "changeLang");
        Route::get("/notifications-status", "changeNotificationState");
        Route::get("/sugs", "adminSugs");
        Route::get("/list", "index")->name(RouteNames::ADMINS_LIST);
        Route::get("/{id}", "show");
        //Only super admin can access this routes
        Route::middleware(['is_super_admin'])->group(function () { //TODO:NEED CHECK FOR DYNAMIC AND POLICIES
            Route::post("/", "store");
            Route::put("/{id}", "update");
            Route::put("/update-image/{id}", "updateProfileImage");
            Route::get("/deactivate/{id}", "deactivateAccount");
        });
    });

    //Users
    Route::prefix("users")->group(function () {
        Route::controller(UserProfileController::class)->group(function () {
            Route::get("/sugs", "userSugs");
            Route::get("/list", "index")->name(RouteNames::USERS_LIST);
            Route::get("/profile/{id}", "show");
            Route::post("/restore", "restore");
        });

        Route::prefix("ban")->controller(BanLogController::class)->group(function () {
            Route::post("/", "ban");
            Route::post("/remove", "unBan");
        });
    });

    //System Info
    Route::prefix("system")->group(function () {
        Route::prefix("about-us")->controller(AboutUsController::class)->group(function () {
            Route::get("/", "index");
            Route::post("/", "store")->withoutMiddleware("xss");
            Route::get("/{lang}", "show");
        });
        Route::prefix("tos")->controller(TosController::class)->group(function () {
            Route::get("/", "index");
            Route::post("/", "store")->withoutMiddleware("xss");
            Route::get("/{lang}", "show");
        });
        Route::prefix("privacy-policy")->controller(PrivacyPolicyController::class)->group(function () {
            Route::get("/", "index");
            Route::post("/", "store")->withoutMiddleware("xss");
            Route::get("/{lang}", "show");
        });
        Route::prefix("faq-category")->controller(FaqCategoryController::class)->group(function () {
            Route::get("/", "index")->name(RouteNames::ADMIN_FAQ_CATEGORY_LIST);
            Route::get("/apps", "apps");
            Route::post("/", "store");
            Route::get("/{id}", "show");
            Route::put("/{id}", "update");
            Route::delete("/{id}", "destroy");
        });
        Route::prefix("faq")->controller(FAQController::class)->group(function () {
            Route::get("/", "indexAdmin")->name(RouteNames::ADMIN_FAQ_LIST);
            Route::post("/", "store");
            Route::get("/{id}", "show");
            Route::put("/{id}", "update");
            Route::delete("/{id}", "destroy");
        });
        Route::prefix("contact-us")->controller(ContactUsController::class)->group(function () {
            Route::get("/", "index");
            Route::get("/types", "types");
            Route::post("/", "store");
            Route::put("/{id}", "update");
            Route::get("/{id}", "show");
            Route::delete("/{id}", "destroy");
        });
        Route::prefix("cities")->controller(CityController::class)->group(function () {
            Route::get("/", "index")->name(RouteNames::ADMIN_CITIES_SELECTABLE_LIST);
            Route::get("/{id}", "show");
        });
        Route::apiResource('/settings', SystemSettingController::class);
    });

    //Logs
    Route::prefix("logs")->group(function () {
        Route::prefix("bans-log")->controller(BanLogController::class)->group(function () {
            Route::get("/", "index")->name(RouteNames::BANLOG_LIST);
            Route::get("/{id}", "show");
        });
    });

    //Notifications
    Route::prefix("notifications")->controller(NotificationController::class)->group(function () {
        Route::get("/list", "index")->name(RouteNames::NOTIFICATIONS_LIST);
        Route::get("/", "getMyNotifications")->name(RouteNames::MY_NOTIFICATIONS_LIST);
        Route::get("/pre-store", "preStore");
        Route::post("/", "storePublic");
        Route::post("/private", "storePrivate");
        Route::get("/{id}", "showAdmin");
        Route::delete("/{id}", "destroy");
    });

    //customer service 
    Route::prefix("customer-cards")->controller(CustomerServiceCardController::class)->group(function () {
        Route::get("/", "indexAdmin")->name(RouteNames::ADMIN_CUSTOMER_CARD_LIST);
        Route::get("/types-status", "getTypesStatus");
        Route::put("/{id}", "update");
        Route::get("/{id}", "showAdmin");
        Route::post("/close", "close");
        Route::delete("/{id}", "destroyByAdmin");
    });

    //Banner & Reels
    // Route::prefix("banners")->controller(BannerController::class)->group(function () {
    //     Route::get("/", "index");
    //     Route::post("/", "store");
    //     Route::put("/{id}", "update");
    //     Route::delete("/{id}", "destroy");
    // });
    // Route::prefix("reels")->controller(ReelController::class)->group(function () {
    //     Route::get("/", "index");
    //     Route::get("/{id}", "show");
    //     Route::post("/", "store");
    //     Route::put("/{id}", "update");
    //     Route::delete("/{id}", "destroy");
    // });
    Route::prefix('story')->controller(StoryController::class)->group(function(){
        Route::get('changeStatus/{id}' , 'changeStatus');
    });
    Route::prefix('banner')->controller(BannerController::class)->group(function(){
        Route::get('changeStatus/{id}' , 'changeStatus');
    });
    Route::prefix('media')->controller(MediaController::class)->group(function(){
        Route::delete('delete' , 'delete');
    });
    Route::prefix('sub_category')->controller(SubCategoryController::class)->group(function(){
        Route::get('getBycategories' , 'getBycategories');
    });
    Route::prefix('plan')->controller(PlanController::class)->group(function(){
        Route::get('changePublishStatus/{id}' , 'changePublishStatus');
    });
    Route::prefix('transactions')->controller(TransactionController::class)->group(function(){
        Route::get('get' , 'getTransactions')->name(RouteNames::ADMIN_TRANSACTION_GET);
    });
    // Route::prefix('doctors')->controller(DoctorController::class)->group(function(){
    //     Route::get('get' , 'getAll');
    //     Route::get('profile/{id}' , 'profile')->name(RouteNames::DOCTORS_GET_PROFILE);
    //     Route::delete('rate/delete/{id}' , 'deleteRate');
    // });
    Route::prefix('patients')->controller(UserController::class)->group(function(){
        Route::get('get' , 'getPatients');
    });
    Route::prefix('article/reactions')->controller(ReactionController::class)->group(function(){
        Route::delete('deleteComment/{id}' , 'unComment');
        Route::delete('deleteReplay/{id}' , 'unReplay');
    });
    Route::prefix('article')->controller(ArticleController::class)->group(function(){
        Route::get('filter' , 'filter');
        Route::delete('delete/{id}' , 'destroy');
        Route::get('show/{id}' , 'show')->name(RouteNames::ARTICLES_SHOW);
    });

    Route::prefix('reservation')->group(function(){
        Route::controller(ReservationController::class)->group(function(){
            Route::post('reject_by_admin/{id}' , 'reject_by_admin');
            Route::get('get/{id}' , 'getReservationsForDoctor')->name(RouteNames::DOCTOR_RESERVATIONS);
            Route::get('getPatient/{id}' , 'getReservationsForUser')->name(RouteNames::PATIENT_RESERVATIONS);
            Route::get('filter' , 'filter')->name(RouteNames::ADMIN_RESERVATIONS);
            Route::get('details/{id}' , 'getReservationDetails')->name(RouteNames::RESERVATION_DETAILS);
            Route::get('analysis/{id}' , 'getReservationAnalysis');
        });
    });

    Route::prefix('patient')->group(function(){
        Route::controller(PatientController::class)->group(function(){
            Route::get('relations/{id}' , 'index')->name(RouteNames::PATIENT_RELATIONS);
        });
    });

    Route::prefix('complaints')->group(function(){
        Route::controller(ComplaintController::class)->group(function(){
            Route::get('process/{id}' , 'process');
        });
    });

    
    
    Route::apiResource('/story', StoryController::class)
        ->name('show' , RouteNames::ADMIN_STORY_GET)
        ->name('index' , RouteNames::ADMIN_STORY_GET);
    Route::apiResource('/banner', BannerController::class)->name('show' , RouteNames::ADMIN_BANNER_GET);
    Route::apiResource('/media', MediaController::class);
    Route::apiResource('/category', CategoryController::class)->name('show' , RouteNames::GET_CATEGORIES);
    Route::apiResource('/sub_category', SubCategoryController::class)->name('show' , RouteNames::GET_SUBCATEGORIES);
    Route::apiResource('/plan', PlanController::class)->name('show' , RouteNames::PLAN_ADMIN);
});