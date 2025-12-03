<?php

namespace App\Http\Resources\Users\Profile;

use Carbon\Carbon;
use App\Traits\ImagesHelper;
use Illuminate\Http\Request;
use App\Http\Resources\DoctorResouce;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
{
    use ImagesHelper;

    public function toArray(Request $request): array
    {
        if (!$this->phone_number && auth()->user() && auth()->user()->isAdmin())
            $phone_number = $this->archivedAccount->phone_number;
        else {
            $phone_number = $this->phone_number ?? "";
        }
        if($this->role_id == 3)
        {
            $data['doctor'] = DoctorResouce::make($this->whenLoaded('doctor'));
        }

        $data = [
            "id"                => $this->id,
            "name"              => $this->name,
            "avatar"            => $this->getProfileImage($this),
            "is_male"           => $this->is_male ? (bool) $this->is_male : null,
            "city_name"         => $this->city["name_" . app()->getLocale()] ?? "",
            "phone_number"      => $phone_number,
            "created_at"        => Carbon::parse($this->created_at)->translatedFormat("Y-m-d g:i a"),
            "is_doctor"         => $this->role_id == 3,
        ];

        //Admin Info
        if (auth()->user() && auth()->user()->role_id != 3) {
            $data += [
                "in_trash"      => (bool) $this->deleted_at,
                "is_active"     => (bool) !$this->deactive_at,
                "is_banned"     => (bool) (!$this->profile || ($this->profile->banned_until && Carbon::parse($this->profile->banned_until)->gt(Carbon::now()))),
            ];
        }

        return $data;
    }
}