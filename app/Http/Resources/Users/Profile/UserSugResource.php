<?php

namespace App\Http\Resources\Users\Profile;

use App\Models\Doctor;
use App\Models\Patient;
use PhpParser\Comment\Doc;
use App\Traits\ImagesHelper;
use Illuminate\Http\Request;
use App\Constants\ApiMessages;
use App\Constants\MediaCollection;
use App\Http\Resources\DoctorResouce;
use App\Http\Resources\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media\DefaultMediaResource;

class UserSugResource extends JsonResource
{
    use ImagesHelper;

    public function toArray(Request $request): array
    {
        if (!$this->phone_number && auth()->user() && auth()->user()->isAdmin())
            $phone_number = $this->archivedAccount->phone_number;
        else {
            $phone_number = $this->phone_number ?? "";
        }
        $patient_owner = Patient::where('user_id' , $this->id)->where('is_owner' , 1)->first();

        if($this->role_id == 3)
        {
            $doctor = $this->Doctor;

            $logo = MediaResource::make($doctor->getFirstMedia(MediaCollection::DOCTOR_LOGO_COLLECTION));

            $logo = ($logo->resource == null) 
                    ? DefaultMediaResource::make(1)
                    : $logo;
        }
        return [
            "id"            => $this->id,
            "name"          => $this->name,
            "avatar"        => ($this->role_id == 3)
            ? $logo->getUrl()
            :$this->getProfileImage($this) ?? "",
            "phone_number"  => $phone_number,
            "role_id"       => $this->role_id,
            "role_name"     => $this->role->name,
        ];
    }
}