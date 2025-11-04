<?php

namespace App\Http\Controllers\Doctor;

use App\Models\Doctor;
use Illuminate\Http\Request;
use App\Constants\ApiMessages;
use App\Http\Controllers\Controller;
use App\Services\Patient\PatientService;
use App\Http\Requests\Article\CommentRequest;
use App\Http\Requests\Media\DeleteMediaRequest;
use App\Http\Requests\Reservation\AcceptRequest;
use App\Http\Requests\Reservation\RejectRequest;
use App\Http\Requests\Reservation\ReportRequest;
use App\Services\Reservation\ReservationService;
use App\Http\Requests\Reservation\UpdateReportRequest;
use App\Http\Resources\Reservation\ReservationResource;
use App\Http\Requests\MedicalProfile\AddMedicinesRequest;
use App\Http\Requests\MedicalProfile\UpdateMedicineRequest;
use App\Http\Requests\MedicalProfile\AddInstructionsRequest;
use App\Http\Requests\MedicalProfile\UpdateInstructionRequest;
use App\Http\Requests\Reservation\UploadVisitMediaRequest;
use App\Http\Requests\UpdateReportMediaRequest;
use App\Http\Requests\UploadReportMediaRequest;
use App\Services\Base\ContextService;

class ReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService,
        protected PatientService $patientService,
        protected ContextService $contextService,
    ){}

    public function reject(RejectRequest $request , $id)
    {
        return success(
            $this->reservationService->reject($id , $request->validated()),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function accept(AcceptRequest $request , $id)
    {
        return success(
            $this->reservationService->accept($id , $request->validated()),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function did_not_come($id)
    {
        return success(
            $this->reservationService->did_not_come($id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function getDatesForDay($day_id)
    {
        return success(
            $this->contextService->getDatesForDay($day_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function getReservations(Request $request)
    {
        return success(
            $this->reservationService->getrDoctorReservations(doctor_id() , $request->all()),
            ApiMessages::MSG_SUCCESS,
            ReservationResource::class,
            $request->has('per_page')
        );
    }

    public function getReservationDetails($id)
    {
        return success(
            $this->reservationService->getReservationDetails($id),
            ApiMessages::MSG_SUCCESS,
            ReservationResource::class
        );
    }

    public function replayOnRate(CommentRequest $request , $rate_id)
    {
        return success(
            $this->reservationService->replayOnRate($rate_id , $request->validated()),
            ApiMessages::MSG_SUCCESS
        );
    }

    // medical report

    public function done(ReportRequest $request , $id)
    {
        return success(
            $this->reservationService->done($id , $request->validated()),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function updateReport(UpdateReportRequest $request , $visit_id)
    {
        return success(
            $this->reservationService->updateReport($visit_id , $request->validated()),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function addMedicines(AddMedicinesRequest $request , $patient_id , $visit_id)
    {
        return success(
            $this->patientService->addMedicines($request->validated() , $patient_id , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function addInstructions(AddInstructionsRequest $request , $patient_id , $visit_id)
    {
        return success(
            $this->patientService->addInstructions($request->validated() , $patient_id , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function updateMedicine(UpdateMedicineRequest $request , $patient_id , $medicine_id , $visit_id)
    {
        return success(
            $this->patientService->updateMedicine($request->validated() , $patient_id , $medicine_id , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function updateInstruction(UpdateInstructionRequest $request , $patient_id , $instruction_id , $visit_id)
    {
        return success(
            $this->patientService->updateInstruction($request->validated() , $patient_id , $instruction_id , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function deleteMedicine($medicine_id , $visit_id)
    {
        return success(
            $this->patientService->deleteMedicine($medicine_id , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function deleteInstruction($instruction_id , $visit_id)
    {
        return success(
            $this->patientService->deleteInstruction($instruction_id , $visit_id), 
            ApiMessages::MSG_SUCCESS
        );
    }

    public function storeMedia(UploadVisitMediaRequest $request , $visit_id)
    {
        return success(
            $this->reservationService->uploadReportMedia($request->validated() , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }
    
    public function deleteMedia(DeleteMediaRequest $request , $visit_id)
    {
        return success(
            $this->reservationService->deleteReportMedia($request->validated() , $visit_id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function getNextReservation($patient_id , $doctor_id)
    {
        return success(
            $this->reservationService->getNextReservation($patient_id , $doctor_id),
            ApiMessages::MSG_SUCCESS,
            ReservationResource::class
        );
    }

    public function doctorFilter(Request $request)
    {
        return success(
            $this->reservationService->filterForDoctor(doctor_id() , $request->all()),
            ApiMessages::MSG_SUCCESS,
            ReservationResource::class
        );
    }
}
