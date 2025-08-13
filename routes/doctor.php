<?php

use App\Constants\RouteNames;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\Doctor\PlanController;
use App\Http\Controllers\Media\MediaController;
use App\Http\Controllers\Doctor\ShiftController;
use App\Http\Controllers\Patient\ListController;
use App\Http\Controllers\Doctor\DoctorController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Doctor\ArticleController;
use App\Http\Controllers\Doctor\PatientController;
use App\Http\Controllers\Doctor\ReservationController;
use App\Http\Controllers\Doctor\TransactionController;

/*
|--------------------------------------------------------------------------
| Doctor API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Doctors API routes for doctors in the system
|
*/

// No Auth Needed
Route::middleware([])->withoutMiddleware('is_doctor')->group(function () {
    Route::prefix('list')->group(function(){
        Route::controller(ListController::class)->group(function(){
            Route::get('categories' , 'categories');
            Route::get('subcategories' , 'subcategories');
            Route::get('cities' , 'cities');
            Route::get('days' , 'days');
        });
    }); 
    
    Route::prefix('plans')->controller(PlanController::class)->group(function () {
        Route::get('get' , 'getPlans');
    });

    Route::prefix('shifts')->controller(ShiftController::class)->group(function () {
        Route::get('{doctor_id}' , 'landingIndex');
    });

    Route::prefix('category')->group(function(){
        Route::controller(CategoryController::class)->group(function(){callback: 
            Route::get('show/{id}' , 'show');
        });
    });
});

//Auth Needed
Route::group(['middleware' => ['auth:api', "is_user", 'token.access_api', 'user.active', 'user.verified']], function () {
    Route::prefix('subscription')->controller(PlanController::class)->group(function () {
        Route::get('subscripe/{id}' , 'subscripe')->middleware('user.banned');
        Route::get('get' , 'getSubscriptions');
    });

    Route::prefix('transactions')->controller(TransactionController::class)->group(function () {
        Route::get('get' , 'getMyTransactions')->name(RouteNames::DOCTOR_TRANSACTION_GET);
    });

    Route::prefix('phone_numbers')->controller(DoctorController::class)->group(function () {
        Route::post('store' , 'addPhoneNumbers');
        Route::post('update/{id}' , 'updatePhoneNumber');
        Route::delete('delete' , 'deletePhoneNumbers');
    });
    Route::prefix('article')->controller(ArticleController::class)->group(function () {
        Route::get('getMine' , 'getMyArticles');
        Route::get('search' , 'search');
        Route::get('show/{id}' , 'show')->name(RouteNames::ARTICLES_SHOW);
    });
    Route::prefix('profile')->controller(DoctorController::class)->group(function () {
        Route::get('getMine' , 'getMyProfile')->name(RouteNames::DOCTORS_GET_PROFILE);
        Route::prefix('certificate')->group(function(){
            Route::post('store' , 'storeCertificate');
            Route::delete('delete' , 'deleteCertificate');
        });
    });
    Route::prefix( 'article/reactions')->controller(ReactionController::class)->group(function(){ 
        Route::get('like/{article_id}' , 'like');
        Route::get('unLike/{article_id}' , 'unLike');
        Route::post('comment/{article_id}' , 'comment');
        Route::get('unComment/{comment_id}' , 'unComment');
        Route::get('likes/{article_id}' , 'getLikes');
        Route::get('comments/{article_id}' , 'getCommentsForUser');
        Route::post('replay/{comment_id}' , 'replay');
        Route::get('unReplay/{replay_id}' , 'unReplay');
    });

    Route::prefix('reservation')->group(function(){
        Route::prefix('media')->controller(ReservationController::class)->group(function(){
            //visit media endpoints
            Route::post('add/{visit_id}' , 'storeMedia');
            Route::delete('delete/{visit_id}' , 'deleteMedia');
        });
        Route::controller(ReservationController::class)->group(function(){
            Route::post('reject/{id}' , 'reject');
            Route::post('accept/{id}' , 'accept');
            Route::get('did_not_come/{id}' , 'did_not_come');
            Route::get('dates/{id}' , 'getDatesForDay');
            Route::post('done/{id}' , 'done');
            Route::get('get' , 'getReservations')->name(RouteNames::DOCTOR_RESERVATIONS);
            Route::get('details/{id}' , 'getReservationDetails')->name(RouteNames::RESERVATION_DETAILS_FOR_DOCTOR);
            Route::post('update/{visit_id}' , 'updateReport');
            //rate
            Route::post('rateReplay/{rate_id}' , 'replayOnRate');

            Route::get('next/{patient_id}/{doctor_id}' , 'getNextReservation');

            Route::prefix('medicalReport')->group(function(){
                Route::prefix('medicine')->group(function(){
                    Route::post('add/{patient_id}/visit/{visit_id}' , 'addMedicines');
                    
                    Route::post('{patient_id}/update/{medicine_id}/visit/{visit_id}' , 'updateMedicine');

                    Route::delete('delete/{medicine_id}/visit/{visit_id}' , 'deleteMedicine');
                });
                Route::prefix('instruction')->group(function(){
                    Route::post('add/{patient_id}/visit/{visit_id}' , 'addInstructions');
                    
                    Route::post('{patient_id}/update/{instruction_id}/visit/{visit_id}' , 'updateInstruction');
                    
                    Route::delete('delete/{instruction_id}/visit/{visit_id}' , 'deleteInstruction');
                });
            });
        });
    });


    Route::prefix('history')->controller(TreatmentController::class)->group(function(){
        Route::get('medicine/{medicine_id}' , 'getMedicineHistory')->name(RouteNames::TREATMENT_DETAILS);
        Route::get('instruction/{instruction_id}' , 'getInstructionHistory')->name(RouteNames::TREATMENT_DETAILS);
    });

    Route::prefix('medical_profile/vaccinations')->controller(PatientController::class)->group(function(){
                Route::get('{id}' , 'getVaccinations');
                Route::post('{vaccination_id}/update/{patient_id}' , 'updateVaccination');
            });
    
    Route::prefix('expired')->controller(TreatmentController::class)->group(function(){
        Route::get('medicine/{patient_id}' , 'getExpiredMedicine')->name(RouteNames::TREATMENT_DETAILS);
        Route::get('instruction/{patient_id}' , 'getExpiredInstruction')->name(RouteNames::TREATMENT_DETAILS);
    });
    
    Route::prefix('home')->controller(DoctorController::class)->group(function(){
        Route::get('' , 'home');
    });
    


    Route::apiResource('/article', ArticleController::class);
    Route::apiResource('/shift', ShiftController::class)
        ->name('index' , RouteNames::DOCTOR_SHIFT_GET)
        ->name('show' , RouteNames::DOCTOR_SHIFT_SHOW);
});