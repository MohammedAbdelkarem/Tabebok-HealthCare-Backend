<?php

namespace App\Services\Reservation;

use Carbon\Carbon;
use App\Models\Day;
use App\Models\Rate;
use App\Models\Shift;
use App\Models\Visit;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Reservation;
use App\Enums\ReactionStatusEnum;
use App\Constants\MediaCollection;
use App\Models\PatientUpdatedInfo;
use App\Services\Plan\PlanService;
use App\Traits\NotificationHelper;
use App\Constants\ExceptionMessages;
use App\Enums\ReservationStatusEnum;
use App\Services\Media\MediaService;
use App\Http\Resources\DoctorResouce;
use App\Services\Base\ContextService;
use App\Constants\NotificationMessages;
use App\Services\Patient\PatientService;
use App\Http\Resources\Media\MediaResource;
use App\Enums\Notifications\NotificationTypes;
use Symfony\Component\Mailer\Messenger\MessageHandler;

/**
 * Class ReservationService.
 */
class ReservationService
{
    use NotificationHelper;
    public function __construct(
        protected ContextService $contextService,
        protected PatientService $patientService,
        protected MediaService $mediaService,
        protected PlanService $planService,
    )
    {}
    public function appoint($data)
    {
        if($data['shift_id'])
        {
            $shift = Shift::findByIdOrFail($data['shift_id']);

            $data['shift_start_time'] = $shift->start_time;
            $data['shift_end_time'] = $shift->end_time;
        }

        $reservation = Reservation::create($data);

        if(isset($data['images']))
            uploadFilesOnMedia($data['images'] , $reservation , MediaCollection::RESERVATION_COLLECTION);

        //     dd(__(NotificationMessages::APPOINTMENT_BOOKED_BODY , 
        
        // [
        //             'name' => $reservation->doctor->clinic_name,
        //             'date' => $reservation->date,
        //             'time' => $reservation->time_to_come
        //         ]
        // ));
            // dd($this->notificationMessage(NotificationMessages::APPOINTMENT_BOOKED_BODY));

        //patient notification
        $this->sendDirectNotification(
            auth()->id(),
            $this->notificationMessage(NotificationMessages::APPOINTMENT_BOOKED_TITLE),
            
            //     $this->notificationMessage(NotificationMessages::APPOINTMENT_BOOKED_BODY),
            $this->notificationMessage(
                NotificationMessages::APPOINTMENT_BOOKED_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                    'date' => $reservation->date,
                    'time' => $reservation->time_to_come
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );

        //doctor notification
        $this->sendDirectNotification(
            user_id_of_doctor($reservation->doctor_id),
            $this->notificationMessage(NotificationMessages::DOCTOR_APPOINTMENT_BOOKED_TITLE),
            
            //     $this->notificationMessage(NotificationMessages::APPOINTMENT_BOOKED_BODY),
            $this->notificationMessage(
                NotificationMessages::DOCTOR_APPOINTMENT_BOOKED_BODY),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
    }

    public function reject($id , $data)
    {
        $this->contextService->checkIfReservationEditorIsValid($id);

        $reservation = Reservation::findByIdOrFail($id);
        
        $this->checkStatusFlow($reservation->status , ReservationStatusEnum::REJECTED->value);

        $reservation->status = ReservationStatusEnum::REJECTED;

        $reservation->rejection_reason = $data['rejection_reason'] ?? null;
        $reservation->other_rejection_reason = $data['other_rejection_reason'] ?? null;

        $reservation->save();


        //patient notification
        $this->sendDirectNotification(
            user_id_of_patient($reservation->patient_id),
            $this->notificationMessage(NotificationMessages::APPOINTMENT_REJECTED_TITLE),
            $this->notificationMessage(
                NotificationMessages::APPOINTMENT_REJECTED_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                    'reason' => $reservation->rejection_reason ?? $reservation->other_rejection_reason,
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
        //doctor notification
        $this->sendDirectNotification(
            auth()->id(),
            $this->notificationMessage(NotificationMessages::DOCTOR_APPOINTMENT_REJECTED_TITLE),
            $this->notificationMessage(
                NotificationMessages::DOCTOR_APPOINTMENT_REJECTED_BODY,
                [
                    'name' => $reservation->patient->full_name,
                    'reason' => $reservation->rejection_reason ?? $reservation->other_rejection_reason,
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
    }

    public function reject_by_admin($id , $data)
    {
        // $this->contextService->checkIfReservationEditorIsValid($id);

        $reservation = Reservation::findByIdOrFail($id);

        $this->checkStatusFlow($reservation->status , ReservationStatusEnum::REJECTED_BY_ADMIN->value);

        $reservation->status = ReservationStatusEnum::REJECTED_BY_ADMIN;

        $reservation->rejection_reason = $data['rejection_reason'] ?? null;
        $reservation->other_rejection_reason = $data['other_rejection_reason'] ?? null;

        $reservation->save();


        //patient notification
        $this->sendDirectNotification(
            user_id_of_patient($reservation->patient_id),
            $this->notificationMessage(NotificationMessages::APPOINTMENT_ADMIN_CANCEL_TITLE),
            $this->notificationMessage(
                NotificationMessages::APPOINTMENT_ADMIN_CANCEL_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                    'reason' => $reservation->rejection_reason ?? $reservation->other_rejection_reason,
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
        //doctor notification
        $this->sendDirectNotification(
            user_id_of_doctor($reservation->doctor_id),
            $this->notificationMessage(NotificationMessages::DOCTOR_APPOINTMENT_ADMIN_CANCEL_TITLE),
            $this->notificationMessage(
                NotificationMessages::DOCTOR_APPOINTMENT_ADMIN_CANCEL_BODY,
                [
                    'reason' => $reservation->rejection_reason ?? $reservation->other_rejection_reason,
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
    }

    public function accept($id , $data)
    {
        $this->contextService->checkIfReservationEditorIsValid($id);

        $reservation = Reservation::findByIdOrFail($id);

        $this->checkStatusFlow($reservation->status , ReservationStatusEnum::ACCEPTED->value);

        $this->checkIfReservationDateIsOnSubscriptionPeriod($data['date'] , $reservation->doctor_id);

        $this->checkIfReservationTimeIsOnDoctorShifts($data['time_to_come'] , $reservation->doctor_id);

        $reservation->status = ReservationStatusEnum::ACCEPTED;

        $reservation->time_to_come = $data['time_to_come'];
        $reservation->date = $data['date'];

        $reservation->save();

        //patient notification
        $this->sendDirectNotification(
            user_id_of_patient($reservation->patient_id),
            $this->notificationMessage(NotificationMessages::APPOINTMENT_CONFIRMED_TITLE),
            $this->notificationMessage(
                NotificationMessages::APPOINTMENT_CONFIRMED_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                    'date' => $reservation->date,
                    'time' => $reservation->time_to_come
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
        //doctor notification
        $this->sendDirectNotification(
            auth()->id(),
            $this->notificationMessage(NotificationMessages::DOCTOR_APPOINTMENT_CONFIRMED_TITLE),
            $this->notificationMessage(
                NotificationMessages::DOCTOR_APPOINTMENT_CONFIRMED_BODY,
                [
                    'name' => $reservation->patient->full_name,
                    'date' => $reservation->date,
                    'time' => $reservation->time_to_come
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
    }

    public function cancel($id)
    {
        $this->contextService->checkIfReservationEditorIsValid($id);

        $reservation = Reservation::findByIdOrFail($id);

        $this->checkStatusFlow($reservation->status , ReservationStatusEnum::CANCELLED->value);

        $this->contextService->checkIfPatientCanCancelReservation($reservation);

        $reservation->status = ReservationStatusEnum::CANCELLED;

        $reservation->save();

        //patient notification
        $this->sendDirectNotification(
            auth()->id(),
            $this->notificationMessage(NotificationMessages::APPOINTMENT_CANCELLED_TITLE),
            $this->notificationMessage(
                NotificationMessages::APPOINTMENT_CANCELLED_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
        //doctor notification
        $this->sendDirectNotification(
            user_id_of_doctor($reservation->doctor_id),
            $this->notificationMessage(NotificationMessages::DOCTOR_APPOINTMENT_CANCELLED_TITLE),
            $this->notificationMessage(
                NotificationMessages::DOCTOR_APPOINTMENT_CANCELLED_BODY,
                [
                    'name' => $reservation->patient->full_name,
                ]
            ),
            NotificationTypes::RESERVATIONS->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
    }

    public function did_not_come($id)
    {
        $this->contextService->checkIfReservationEditorIsValid($id);

        $reservation = Reservation::findByIdOrFail($id);

        $this->checkStatusFlow($reservation->status , ReservationStatusEnum::DID_NOT_COME->value);

        $reservation->status = ReservationStatusEnum::DID_NOT_COME;

        $reservation->save();


        //patient notification
        $this->sendDirectNotification(
            user_id_of_patient($reservation->patient_id),
            $this->notificationMessage(NotificationMessages::APPOINTMENT_DID_NOT_COME_TITLE),
            $this->notificationMessage(
                NotificationMessages::APPOINTMENT_DID_NOT_COME_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                ]
            ),
            NotificationTypes::MEDICAL_PROFILE->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
        //doctor notification
        $this->sendDirectNotification(
            auth()->id(),
            $this->notificationMessage(NotificationMessages::DOCTOR_APPOINTMENT_DID_NOT_COME_TITLE),
            $this->notificationMessage(
                NotificationMessages::DOCTOR_APPOINTMENT_DID_NOT_COME_BODY,
                [
                    'name' => $reservation->patient->full_name,
                ]
            ),
            NotificationTypes::MEDICAL_PROFILE->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );
    }

    public function done($id , $data)
    {
        $this->contextService->checkIfReservationEditorIsValid($id);

        //report
        $reservation = Reservation::findByIdOrFail($id);

        $this->checkStatusFlow($reservation->status , ReservationStatusEnum::DONE->value);

        $reservation->status = ReservationStatusEnum::DONE;

        $reservation->save();

        $visit = Visit::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'doctor_id' => $reservation->doctor_id,
            'patient_id' => $reservation->patient_id,
            'reservation_id' => $id,
            'note' => $data['notes'] ?? null,
        ]);

        if(isset($data['attachments']))
            uploadFilesOnMedia($data['attachments'] , $visit , MediaCollection::VISIT_COLLECTION);

        //patient updated info

        $patient = Patient::find($reservation->patient_id);

        if($data['patient_info_updated'])
        {
            $this->storePatientUpdatedInfo($visit , $patient , $data);    
        
            $this->updatePatientTableInfo($data , $patient);
        }

        //the next reservation

        $day_name = Carbon::parse($data['next_date'])->format('l');

        $day_id = Day::where('name' , $day_name)->first()->id;

        if(isset($data['next_date']))
        {
            Reservation::create([
                'text' => $data['next_text'] ?? null,
                'notes' => $data['next_notes'] ?? null,
                'date'  => $data['next_date'] ?? null,
                'day_id'  => $day_id,
                'time_to_come' => $data['time_to_come'],
                'status' => ReservationStatusEnum::ACCEPTED,
                'patient_id' => $reservation->patient_id,
                'doctor_id' => $reservation->doctor_id,
            ]);
        }

        //medicines and intructions
        if(isset($data['medicines']))
            $this->patientService->storeMedicinesData($data , $patient->id , $visit->id);
        if(isset($data['instructions']))
            $this->patientService->storeInstructionsData($data , $patient->id , $visit->id);

        $this->sendDirectNotification(
            user_id_of_patient($visit->patient_id),
            $this->notificationMessage(NotificationMessages::MEDICAL_REPORT_TITLE),
            $this->notificationMessage(
                NotificationMessages::MEDICAL_REPORT_BODY,
                [
                    'name' => $reservation->doctor->clinic_name,
                ]
            ),
            NotificationTypes::MEDICAL_PROFILE->value,
            'ar',
            false,
            $reservation->id,
            [],
            true,
            [],
            true
        );

    }

    public function updateReport($visit_id , $data)
    {
        $visit = Visit::findByIdOrFail($visit_id);

        $patient = Patient::findByIdOrFail($visit->patient_id);

        $this->contextService->checkIfDoctorCanEditOrChatWithPatient($visit);

        $visit->update([
            'title' => $data['title'] ?? $visit->title,
            'description' => $data['description'] ?? $visit->description,
            'note' => $data['notes'] ?? $visit->notes
        ]);

        if($data['patient_info_updated'])
        {
            $recordExist = $visit->patientUpdatedInfo()->where('visit_id' , $visit->id)->exists();

            if(!$recordExist)
                $this->storePatientUpdatedInfo($visit , $patient , $data);
            else{
                if($patient->is_owner == 1 && isset($data['new_weight']) && $patient->weight != $data['new_weight'])
                    $this->contextService->createWeightHistory($patient->id , $patient->weight , $data['new_weight']);
                
                $visit->patientUpdatedInfo()->update([
                    'current_height' => $data['new_height'] ?? null,
                    'current_weight' => $data['new_weight'] ?? null,
                    'current_blood_type' => $data['new_blood_type'] ?? null,
                    'current_chronic_diseases' => $data['new_chronic_diseases'] ?? null,
                    'current_notes' => $data['new_notes'] ?? null,
                ]);
            }
            
            $this->updatePatientTableInfo($data , $patient);
        }
    }

    public function uploadReportMedia($data , $visit_id)
    {
        $visit = Visit::findByIdOrFail($visit_id);

        $this->contextService->checkIfDoctorCanEditOrChatWithPatient($visit);

        uploadFileOnMedia($data , $visit , MediaCollection::VISIT_COLLECTION);
    }

    public function deleteReportMedia($data , $visit_id)
    {
        $visit = Visit::findByIdOrFail($visit_id);

        $this->contextService->checkIfDoctorCanEditOrChatWithPatient($visit);

        $this->mediaService->delete($data);
    }


    private function storePatientUpdatedInfo($visit , $patient , $data)
    {
        if($patient->is_owner == 1 && isset($data['new_weight']) && $patient->weight != $data['new_weight'])
            $this->contextService->createWeightHistory($patient->id , $patient->weight , $data['new_weight']);
        
        $visit->patientUpdatedInfo()->create([
            'old_height' => $patient->height,
            'old_weight' => $patient->weight,
            'old_blood_type' => $patient->blood_type,
            'old_chronic_diseases' => $patient->chronic_diseases,
            'old_notes' => $patient->notes,
            
            'current_height' => $data['new_height'] ?? null,
            'current_weight' => $data['new_weight'] ?? null,
            'current_blood_type' => $data['new_blood_type'] ?? null,
            'current_chronic_diseases' => $data['new_chronic_diseases'] ?? null,
            'current_notes' => $data['new_notes'] ?? null,
        ]);
    }

    private function updatePatientTableInfo($data , $patient)
    {
        $infos = [
            'height',
            'weight',
            'blood_type',
            'chronic_diseases',
            'notes'
        ];
        
        foreach($infos as $info)
            $patient->$info = $data['new_' . $info] ?? $patient->$info;

        $patient->save();
    }

    public function rateVisit($visit_id , $data)
    {
        $visit = Visit::findByIdOrFail($visit_id);

        $this->checkIfHasBeenRated($visit);

        $rate = Rate::create([
            'patient_id' => $visit->patient_id,
            'doctor_id' => $visit->doctor_id,
            'visit_id' => $visit->id,
            'rate' => $data['rate'],
            'comment' => $data['comment'] ?? null,
        ]);

        if(isset($data['images']))
            uploadFilesOnMedia($data['images'] , $rate , MediaCollection::RATE_COLLECTION);

        $visit->rate_reminded = 1;

        $visit->save();
        
        $doctor = Doctor::find($visit->doctor_id);

        $doctor->rate_sum += $data['rate'];
        $doctor->rate_counter++;
        $doctor->total_rate = $doctor->rate_sum / $doctor->rate_counter;

        $doctor->save();
    }

    public function replayOnRate($rate_id , $data)
    {
        $rate = Rate::find($rate_id);

        $this->checkIfHasBeenReplayedOnRate($rate);

        $rate->doctor_replay = $data['comment'];

        $rate->save();
    }

    public function deleteRate($id)
    {
        $rate = Rate::find($id);

        $rate->delete();
    }

    public function getReservationAnalysis($doctor_id)
    {
        $totalReservationsCount = Reservation::where('doctor_id' , $doctor_id)
                ->whereIn('status' , [
                    ReservationStatusEnum::DONE->value,
                    ReservationStatusEnum::ACCEPTED->value,
                    ReservationStatusEnum::REJECTED->value,
                    ReservationStatusEnum::REJECTED_BY_ADMIN->value,
                ])
                ->count();
        $acceptedReservationsCount = Reservation::where('doctor_id' , $doctor_id)
                ->whereIn('status' , [
                    ReservationStatusEnum::DONE->value,
                    ReservationStatusEnum::ACCEPTED->value,
                ])
                ->count();
        $rejectedReservationsCount = Reservation::where('doctor_id' , $doctor_id)
                ->whereIn('status' , [
                    ReservationStatusEnum::REJECTED->value,
                    ReservationStatusEnum::REJECTED_BY_ADMIN->value,
                ])
                ->count();
        
        // Initialize percentages
        $acceptedPercentage = 0;
        $rejectedPercentage = 0;

        // Calculate percentages if total reservations count is greater than zero
        if ($totalReservationsCount > 0) {
            $acceptedPercentage = ($acceptedReservationsCount / $totalReservationsCount) * 100;
            $rejectedPercentage = ($rejectedReservationsCount / $totalReservationsCount) * 100;
        }

        return [
            'total_reservations' => $totalReservationsCount,
            'accepted_reservations' => $acceptedReservationsCount,
            'rejected_reservations' => $rejectedReservationsCount,
            'accepted_percentage' => round($acceptedPercentage, 2), // Round to 2 decimal places
            'rejected_percentage' => round($rejectedPercentage, 2), // Round to 2 decimal places
        ];
    }

    private function getReservations($doctor_id , $patient_ids , $data , $with = [])
    {
        return getOrPaginate(
            Reservation::filter($data , $doctor_id , $patient_ids)
            ->with($with),
            $data
        );
    }

    public function getUserReservations($data , $user_id = null)
    {
        $id = $user_id ?? auth()->id();

        $patient_ids = Patient::where('user_id' , $id)->pluck('id');
        
        return $this->getReservations(null  ,$patient_ids , $data , [
            'doctor.subCategories.category' ,
             'doctor.user' ,
            //   'patient.medicines.medicine_days.medicine_times' ,
            //   'patient.instructions' ,
              'patient.user' ,
            //   'patient.reservations.doctor.user' ,
            //   'patient.reservations.doctor.subCategories.category' ,
            //   'patient.reservations.visit.medicines.medicine_days.day' ,
            //   'patient.reservations.visit.medicines.medicine_days.medicine_times' ,
            //   'patient.reservations.visit.instructions' ,
            //   'patient.reservations.visit.patientUpdatedInfo' ,
                'visit.rate' ,
                 'visit.patientUpdatedInfo' ,
                  'visit.medicines.medicine_days.day' ,
                  'visit.medicines.medicine_days.medicine_times' ,
                   'visit.instructions',
                   'complaints.patient',
                  'complaints.doctor',
                //   'complaints.reservation',
        ]);
    }

    public function getrDoctorReservations($doctor_id , $data)
    {
        return $this->getReservations($doctor_id , null , $data , [
            'patient',
            'visit'
        ]);
    }

    public function filterReservations($data)
    {
        return $this->getReservations(null , null , $data ,[
            'doctor.subCategories',
            'patient',
            'visit'
        ]);
    }

    public function getReservationDetails($reservation_id)
    {
        $reservation = Reservation::findByIdOrFail($reservation_id , [
             'doctor.subCategories.category' ,
             'doctor.user' ,
              'patient.medicines.medicine_days.medicine_times' ,
              'patient.instructions' ,
              'patient.user' ,
              'patient.reservations.doctor.user' ,
              'patient.reservations.doctor.subCategories.category' ,
              'patient.reservations.visit.medicines.medicine_days.day' ,
              'patient.reservations.visit.medicines.medicine_days.medicine_times' ,
              'patient.reservations.visit.instructions' ,
              'patient.reservations.visit.patientUpdatedInfo' ,
                'visit.rate' ,
                 'visit.patientUpdatedInfo' ,
                  'visit.medicines.medicine_days.day' ,
                  'visit.medicines.medicine_days.medicine_times' ,
                   'visit.instructions',
                   'complaints.patient',
                  'complaints.doctor',
                  'complaints.reservation',
        ]);

        return $reservation;
    }

    public function getNextReservation($patient_id , $doctor_id)
    {
        return Reservation::where('patient_id' , $patient_id)
            ->where('doctor_id' , $doctor_id)
            ->whereIn('status' , [
                ReservationStatusEnum::ACCEPTED->value,
                ReservationStatusEnum::PENDING->value,
            ])
            ->latest('id')
            ->first();
    }

    public function filterForDoctor($doctor_id , $data)
    {
        return getOrPaginate(
            Reservation::doctorFilter($data , $doctor_id)->with(
                'patient',
                'visit'
            ),
            $data
        );
    }

    private function checkStatusFlow($old_status , $new_status)
    {
        if(
            $old_status == ReservationStatusEnum::PENDING->value &&
             ($new_status == ReservationStatusEnum::DONE->value || $new_status == ReservationStatusEnum::DID_NOT_COME->value)

            || 

            $new_status == ReservationStatusEnum::DONE->value && $old_status != ReservationStatusEnum::ACCEPTED->value

            ||
            
            $old_status == ReservationStatusEnum::ACCEPTED->value && $new_status == ReservationStatusEnum::REJECTED->value

            ||

            (
                $old_status == ReservationStatusEnum::REJECTED->value
            || $old_status == ReservationStatusEnum::CANCELLED->value 
            || $old_status == ReservationStatusEnum::DONE->value 
            || $old_status == ReservationStatusEnum::DID_NOT_COME->value 
            || $old_status == ReservationStatusEnum::REJECTED_BY_ADMIN->value 
            )
        )
        {
            return forbiddenFailure([] , __(ExceptionMessages::MSG_RESERVATION_STATUS_FLOW_ERROR , ['old_status' => $old_status , 'new_status' => $new_status]));
        }
    }

    private function checkIfHasBeenRated($visit)
    {
        if($visit->rate()->exists())
            return forbiddenFailure([] , ExceptionMessages::MSG_CAN_NOT_RATE_AGAIN);
    }

    private function checkIfHasBeenReplayedOnRate($rate)
    {
        if($rate->doctor_replay != null)
            return forbiddenFailure([] , ExceptionMessages::MSG_RATE_ALREADY_HAS_REPLAY);
    }

    private function checkIfReservationDateIsOnSubscriptionPeriod($reservation_date , $doctor_id)
    {
        $latest_paln_date = $this->planService->getDateToStartNewSubscription($doctor_id);

        if($reservation_date > $latest_paln_date)
            return forbiddenFailure(null , __(ExceptionMessages::MSG_PLAN_EXPIRED , ['date' => $latest_paln_date]));
    }

    private function checkIfReservationTimeIsOnDoctorShifts($time_to_come , $doctor_id)
    {
        $valid_shift = Shift::where('doctor_id' , $doctor_id)
                    ->where('start_time', '<=', $time_to_come)
                    ->where('end_time', '>=', $time_to_come)
                    ->exists();

        if(! $valid_shift)
            return forbiddenFailure(null , ExceptionMessages::MSG_INVALID_SHIFT);
    }

}
