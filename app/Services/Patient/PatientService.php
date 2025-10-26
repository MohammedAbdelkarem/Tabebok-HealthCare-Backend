<?php

namespace App\Services\Patient;

use App\Models\Step;
use App\Models\Story;
use App\Models\Visit;
use App\Models\Banner;
use App\Models\Doctor;
use App\Models\Article;
use App\Models\Patient;
use App\Models\Medicine;
use App\Models\Instruction;
use App\Models\MedicineDay;
use App\Models\Reservation;
use App\Models\MedicineTime;
use App\Traits\StorageHelper;
use App\Models\TreatmentHistory;
use App\Enums\TreatmentStatusEnum;
use App\Services\User\UserService;
use App\Traits\NotificationHelper;
use App\Constants\ExceptionMessages;
use App\Enums\ReservationStatusEnum;
use App\Http\Resources\DoctorResouce;
use App\Services\Base\ContextService;
use App\Constants\NotificationMessages;
use App\Enums\Notifications\NotificationTypes;
use App\Http\Resources\Article\ArticleResource;
use App\Http\Resources\Banner\BannerResource;
use App\Http\Resources\Reservation\ReservationResource;
use App\Http\Resources\Story\StoryResource;
use App\Services\Base\ProcessDataService;
use App\Services\System\Notification\NotificationService;
use App\Services\Vaccination\VaccinationService;

/**
 * Class PatientService.
 */
class PatientService
{
    use StorageHelper , NotificationHelper;

    public function __construct(
        protected UserService $userService,
        protected ContextService $contextService,
        protected ProcessDataService $processDataService,
        protected NotificationService $notificationService,
        protected VaccinationService $vaccinationService,
    ) {}
    
    public function getMyRelations($user_id = null)
    {
        $id = $user_id ?? auth()->id();

        $patients = Patient::where('user_id', $id)->with([
            'instructions' ,
               'medicines.medicine_days.day' ,
                'medicines.medicine_days.medicine_times' ,
                  'reservations.doctor.subCategories.category',
                  'reservations.doctor.user',
                    'reservations.visit.medicines.medicine_days.day' ,
                    'reservations.visit.medicines.medicine_days.medicine_times' ,
                    'reservations.visit.instructions' ,
                    'reservations.visit.patientUpdatedInfo' ,
                  'user',
                  'complaints.patient',
                  'complaints.doctor',
                  'complaints.reservation',
            ])->get();
        
        if($user_id != null)
            foreach($patients as $patient)
                $patient->unsetRelation('reservations');

        return $patients;
    }
    public function createMyMedicalProfile($data)
    {
        $hasBeenCreated = Patient::where('user_id', auth()->id())
                        ->where('is_owner' , 1)
                        ->exists();
        if($hasBeenCreated)
            return forbiddenFailure([] , ExceptionMessages::MSG_MEDICAL_PROFILE_ALREADY_EXIST);

        $patientData = [
            'is_owner' => 1,
            'user_id' => auth()->id(),
            'full_name' => auth()->user()->name ?? $data['full_name'] ?? null,
            'birth_date' => auth()->user()->birth_date ?? $data['birth_date'] ?? null,
            'is_male' => auth()->user()->is_male ?? $data['is_male'] ?? null,
            'avatar' => auth()->user()->avatar ?? null,
            'relation' => 'me',
            'smoking' => $data['smoking'] ?? null,
            'alcohol' => $data['alcohol'] ?? null,
            'height' => $data['height'] ?? null,
            'weight' => $data['weight'] ?? null,
            'blood_type' => $data['blood_type'] ?? null,
            'chronic_diseases' => $data['chronic_diseases'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        // $patientData['avatar'] = $data['avatar'] ?? null;

        $patient = $this->storePatientData($patientData);

        if (isset($data["avatar"]) && $patient->avatar == null) {
            $patient->avatar = $this->storeFile(
                file: $data["avatar"],
                path: "users/{$patient->id}"
            );
        }

        // if(isset($data['medicines']))
        //     $this->storeMedicinesData($data , $patient->id);
        // if(isset($data['instructions']))
        //     $this->storeInstructionsData($data , $patient->id);

        $patient->save();
    }

    public function createMedicalProfile($data)
    {
        $patientData = [
            'user_id' => auth()->id(),
            'full_name' => $data['full_name'] ?? null,
            'birth_date' =>  $data['birth_date'] ?? null,
            'is_male' => $data['is_male'] ?? null,
            // 'avatar' => $data['avatar'] ?? null,
            'relation' => $data['relation'] ?? null,
            'smoking' => $data['smoking'] ?? null,
            'alcohol' => $data['alcohol'] ?? null,
            'height' => $data['height'] ?? null,
            'weight' => $data['weight'] ?? null,
            'blood_type' => $data['blood_type'] ?? null,
            'chronic_diseases' => $data['chronic_diseases'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $patient = $this->storePatientData($patientData);

        if (isset($data["avatar"])) {
            $patient->avatar = $this->storeFile(
                file: $data["avatar"],
                path: "users/{$patient->id}"
            );
        }

        // if(isset($data['medicines']))
        //     $this->storeMedicinesData($data , $patient->id);
        // if(isset($data['instructions']))
        //     $this->storeInstructionsData($data , $patient->id);

        $patient->save();
    }

    public function updatePatientInfo($data , $patient_id)
    {
        $patient = Patient::findByIdOrFail($patient_id);

        if(owner_id() != null && $patient_id == owner_id())
        {
            $data['relation'] = 'me';

            if(isset($data['weight']) && $patient->weight != $data['weight'])
                $this->contextService->createWeightHistory($patient->id , $patient->weight , $data['weight']);
            // dd($patient->is_owner , $data['weight'] , $patient->weight);
        
            $this->userService->updateOwnerInfo($data);
        }
        
        $patient->update($data);

        if (isset($data["avatar"])) {
                $patient = $this->StoreUpdate(
                file: $data["avatar"],
                path: "patients/{$patient->id}",
                model: $patient,
                column: "avatar",
                deleteImage: true,
                singleFilePath: $patient->avatar ?? ""
            );
        }

        $patient->save();
    }

    public function addMedicines($data , $patient_id , $visit_id = null)
    {
        if(auth()->user()->isDoctor() && $visit_id != null)
            $this->checkIfCanEditTreatmentsByDoctor(Visit::findByIdOrFail($visit_id));

        $this->storeMedicinesData($data , $patient_id , $visit_id);
    }

    public function addInstructions($data , $patient_id , $visit_id = null)
    {
        if(auth()->user()->isDoctor() && $visit_id != null)
            $this->checkIfCanEditTreatmentsByDoctor(Visit::findByIdOrFail($visit_id));

        $this->storeInstructionsData($data , $patient_id , $visit_id);
    }

    public function updateMedicine($data , $patient_id , $medicine_id , $visit_id = null)
    {
        //the old medicine
        $medicine = Medicine::findByIdOrFail($medicine_id);

        $this->checkIfExpiredBeforeEditing($medicine);

        if(auth()->user()->isPatient())
            $this->checkIfCanEditTreatmentsByPatient($medicine);
        if(auth()->user()->isDoctor())
            $this->checkIfCanEditTreatmentsByDoctor(Visit::findByIdOrFail($visit_id));

        $finalData['medicines'][0] = $data;

        $this->storeMedicinesData($finalData , $patient_id , $visit_id , $medicine_id);
    }

    public function updateInstruction($data , $patient_id , $instruction_id , $visit_id = null)
    {
        //the old instruction
        $instruction = Instruction::findByIdOrFail($instruction_id);

        $this->checkIfExpiredBeforeEditing($instruction);

        if(auth()->user()->isPatient())
            $this->checkIfCanEditTreatmentsByPatient($instruction);
        if(auth()->user()->isDoctor())
            $this->checkIfCanEditTreatmentsByDoctor(Visit::findByIdOrFail($visit_id));

        $finalData['instructions'][0] = $data;

        $this->storeInstructionsData($finalData , $patient_id , $visit_id , $instruction_id);
    }

    public function deleteMedicine($medicine_id , $visit_id = null)
    {
        $medicine = Medicine::findByIdOrFail($medicine_id , [
            'medicine_days.day',
            'medicine_days.medicine_times',
        ]);

        $this->checkIfExpiredBeforeEditing($medicine);

        if(auth()->user()->isPatient())
            $this->checkIfCanEditTreatmentsByPatient($medicine);
        if(auth()->user()->isDoctor())
            $this->checkIfCanEditTreatmentsByDoctor(Visit::findByIdOrFail($visit_id));

        $finalData['medicines'][0] = $medicine->toArray();

        $this->storeMedicinesData($finalData , $medicine->patient_id , $visit_id , $medicine_id , true);
    }

    public function deleteInstruction($instruction_id , $visit_id = null)
    {
        $instruction = Instruction::findByIdOrFail($instruction_id);

        $this->checkIfExpiredBeforeEditing($instruction);

        if(auth()->user()->isPatient())
            $this->checkIfCanEditTreatmentsByPatient($instruction);
        if(auth()->user()->isDoctor())
            $this->checkIfCanEditTreatmentsByDoctor(Visit::findByIdOrFail($visit_id));

        $finalData['instructions'][0] = $instruction->toArray();

        $this->storeInstructionsData($finalData , $instruction->patient_id , $visit_id , $instruction_id , true);
    }

    private function storePatientData($data)
    {
        $patient = Patient::create($data);

        if($patient->is_owner == 1)
        {
            $this->userService->updateOwnerInfo($data);
            $this->contextService->createWeightHistory($patient->id , 0 , $data['weight']);
        }

        $this->vaccinationService->createVaccinations($patient);

        return $patient;
    }

    public function storeMedicinesData($data , $patient_id = null , $visit_id = null , $old_medicine_id = null , $setAsExpired = false)
    {
        // dd($data);
        foreach($data['medicines'] as $medicine)
        {
            $medicine['patient_id'] = $patient_id;
            $medicine['visit_id'] = $visit_id; //could be null

            //added by?
            $medicine['userable_id'] = (auth()->user()->isDoctor())
            ? doctor_id()
            : $patient_id;
            $medicine['userable_type'] = (auth()->user()->isDoctor())
            ? Doctor::class
            : Patient::class;

            $medicine['status'] = ($setAsExpired)
            ? TreatmentStatusEnum::EXPIRED
            : $medicine['status'];
            
            
            $one_medicine = Medicine::create($medicine);

            $finalHistoryArray = [];

            if($old_medicine_id != null)
            {
                $old_medicine = Medicine::findByIdOrFail($old_medicine_id);
                $old_medicine->is_latest = 0;
                $old_medicine->save();

                $history_ids = TreatmentHistory::where('itemable_id', $old_medicine->id)
                        ->where('itemable_type', Medicine::class)
                        ->first()->history_ids;

                //getting the old history of this medicine
                $finalHistoryArray = $history_ids;
            }

            //adding the new medicine id to the history
            $finalHistoryArray[] = $one_medicine->id;

            TreatmentHistory::create([
                'itemable_id' => $one_medicine->id,
                'itemable_type' => Medicine::class,
                'history_ids' => $finalHistoryArray
            ]);

            if(isset($medicine['days']))
            {
                foreach($medicine['days'] as $one_day)
                {
                    // dd($one_day['day_id']);
                    $medicine_day = MedicineDay::create([
                        'day_id' => $one_day['day_id'],
                        'medicine_id' => $one_medicine->id
                    ]);

                    if(isset($one_day['time']))
                    {
                        foreach($one_day['time'] as $one_time)
                        {
                            // dd($one_time);
                            MedicineTime::create([
                                'medicine_day_id' => $medicine_day->id,
                                'time' => $one_time,
                            ]);
                        }
                    }
                    if(isset($one_day['other_time']))
                    {
                        foreach($one_day['other_time'] as $other_time)
                        {
                            MedicineTime::create([
                                'medicine_day_id' => $medicine_day->id,
                                'other_time' => $other_time,
                            ]);
                        }
                    }
                }
            }
        }
        if(auth()->user()->isDoctor())
        {
            $doctor = Doctor::find(doctor_id());
            $this->sendDirectNotification(
                user_id_of_patient($patient_id),
                $this->notificationMessage(NotificationMessages::NEW_PRESCRIPTION_TITLE),
                $this->notificationMessage(
                    NotificationMessages::NEW_PRESCRIPTION_BODY,
                    [
                        'name' => $doctor->clinic_name,
                    ]
                ),
                NotificationTypes::MEDICAL_PROFILE->value,
                'ar',
                false,
                "",
                [],
                true,
                [],
                true
            );
        }
    }

    public function storeInstructionsData($data , $patient_id = null , $visit_id = null , $old_instruction_id = null , $setAsExpired = false)
    {
        foreach($data['instructions'] as $instruction)
        {
            $instruction['patient_id'] = $patient_id;
            $instruction['visit_id'] = $visit_id; //could be null

            //added by?
            $instruction['userable_id'] = (auth()->user()->isDoctor())
            ? doctor_id()
            : $patient_id;
            $instruction['userable_type'] = (auth()->user()->isDoctor())
            ? Doctor::class
            : Patient::class;

            $instruction['status'] = ($setAsExpired)
            ? TreatmentStatusEnum::EXPIRED
            : $instruction['status'];
            
            $instruction = Instruction::create($instruction);

            $finalHistoryArray = [];

            if($old_instruction_id != null)
            {
                $old_instruction = Instruction::findByIdOrFail($old_instruction_id);
                $old_instruction->is_latest = 0;
                $old_instruction->save();

                $history_ids = TreatmentHistory::where('itemable_id', $old_instruction->id)
                        ->where('itemable_type', Instruction::class)
                        ->first()->history_ids;

                //getting the old history of this instruction
                $finalHistoryArray = $history_ids;
            }

            //adding the new instruction id to the history
            $finalHistoryArray[] = $instruction->id;

            TreatmentHistory::create([
                'itemable_id' => $instruction->id,
                'itemable_type' => Instruction::class,
                'history_ids' => $finalHistoryArray
            ]);
        }

        if(auth()->user()->isDoctor())
        {
            $doctor = Doctor::find(doctor_id());
            $this->sendDirectNotification(
                user_id_of_patient($patient_id),
                $this->notificationMessage(NotificationMessages::NEW_RECOMMENDATION_TITLE),
                $this->notificationMessage(
                    NotificationMessages::NEW_RECOMMENDATION_BODY,
                    [
                        'name' => $doctor->clinic_name,
                    ]
                ),
                NotificationTypes::MEDICAL_PROFILE->value,
                'ar',
                false,
                "",
                [],
                true,
                [],
                true
            );
        }
    }

    public function getProfileForPermanetTreatments($patient_id)
    {
        return Patient::findByIdOrFail($patient_id , [
            'permanent_instructions' , 
            'permanent_medicines',
            'user'
        ]);
    }

    private function checkIfCanEditTreatmentsByPatient($context)
    {
        if(($context->visit_id != null || $context->userable_type != Patient::class))
        {
            return forbiddenFailure([] , ExceptionMessages::MSG_CANT_EDIT_TREATMENTS_IN_VISIT);
        }
    }

    private function checkIfCanEditTreatmentsByDoctor($visit)
    {
        $this->contextService->checkIfDoctorCanEditOrChatWithPatient($visit);
    }

    private function checkIfExpiredBeforeEditing($context)
    {
        if($context->status == TreatmentStatusEnum::EXPIRED->value)
            return forbiddenFailure([] , ExceptionMessages::MSG_CAN_NOT_UPDATE_EXPIRED);
        if($context->is_latest == 0)
            return forbiddenFailure([] , ExceptionMessages::MSG_CAN_NOT_UPDATE_HISTORY);
    }

    public function home()
    {
        $stories = StoryResource::collection(Story::active()->get());

        $banners = BannerResource::collection(Banner::active()->get());

        // $patient = Patient::findByIdOrFail(owner_id());

        $step = Step::where('user_id' , auth()->id())
            ->with(['steps_times'])
            ->latest('id')
            ->first();

        $medicine = Medicine::where('patient_id' , owner_id())
            ->with([
                'medicine_days.day',
                'medicine_days.medicine_times',
            ])
            ->latest('id')
            ->first();

        $instruction = Instruction::where('patient_id' , owner_id())
            ->latest('id')
            ->first();

        $patientsIds = Patient::where('user_id' , auth()->id())
                    ->pluck('id')
                    ->toArray();
        

        $latestReservation = null;

        if (!empty($patientsIds)) {
            $latestReservation = Reservation::whereIn('status', [
                    ReservationStatusEnum::ACCEPTED->value,
                    ReservationStatusEnum::PENDING->value,
                ])
                ->whereIn('patient_id', $patientsIds)
                ->with([
                    'doctor.subCategories.category',
                    'doctor.user',
                ])
                ->orderBy('updated_at', 'desc')
                ->first();
        }


        $reservations = ReservationResource::collection(Reservation::where('patient_id' , owner_id())
            ->with([
                'doctor.subCategories.category',
                'doctor.user',
                'visit.rate',
            ])
            ->get());

        $doctors = DoctorResouce::collection(Doctor::notBanned()
                ->where('rate_sum' , '>' , 3)
                ->get());
            
        $articles = ArticleResource::collection(Article::inRandomOrder()->with(
            [
                'existsReactions.user' ,
             'existsReactions.existReplays' ,
              'existsComments.user',
              'existsComments.existReplays',
              'doctor.subCategories.category'
            ]
        )->get());

        $unreadNotificationsCount = $this->notificationService->getUnreadNotificationsCount();


        $this->processDataService->processTreatments();
        
        return [
            'stories' => $stories,
            'banners' => $banners,
            'step' => $step,
            'medicine' => $medicine,
            'instruction' => $instruction,
            'latestReservation' => $latestReservation,
            'reservations' => $reservations,
            'doctors' => $doctors,
            'articles' => $articles,
            'unreadNotificationsCount' => $unreadNotificationsCount
        ];
    }

    public function deletePatient($patient_id)
    {
        $patient = Patient::findByIdOrFail($patient_id);

        if($patient->is_owner)
            return unprocessableFailure([] , ExceptionMessages::MSG_CAN_NOT_DELETE_OWNER);

        $patient->is_active = 0;

        $patient->save();
        
    }
}
