<?php

namespace App\Http\Controllers\Doctor;

use App\Constants\ApiMessages;
use App\Constants\ExceptionMessages;
use App\Models\Shift;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\CreateShiftsRequest;
use App\Http\Requests\Shift\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Services\Shift\ShiftService;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    public function index()
    {
        return success(
            $this->shiftService->getDoctorShifts( doctor_id()),
            ApiMessages::MSG_SUCCESS,
        );
    }

    public function landingIndex($doctor_id)
    {
        return success(
            $this->shiftService->getDoctorShifts( $doctor_id),
            ApiMessages::MSG_SUCCESS,
        );
    }
    public function show($id)
    {
        return success(
            $this->shiftService->show($id),
            ApiMessages::MSG_SUCCESS,
            ShiftResource::class,
        );
    }
    public function store(CreateShiftsRequest $request)
    {
        return createdSuccess(
            $this->shiftService->storeShifts($request->validated()),
            ApiMessages::MSG_SUCCESS
        );
    }
    public function update(UpdateShiftRequest $request , $id)
    {
        return success(
            $this->shiftService->updateShift($request->validated() , $id),
            ApiMessages::MSG_SUCCESS
        );
    }

    public function destroy($id)
    {
        return success(
            $this->shiftService->deleteShift($id),
            ApiMessages::MSG_SUCCESS
        );
    }
}
