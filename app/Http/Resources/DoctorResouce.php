<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Constants\RouteNames;
use App\Constants\MediaCollection;
use App\Http\Resources\Article\ArticleResource;
use App\Http\Resources\Media\DefaultMediaResource;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Rate\RateResource;
use App\Http\Resources\SubCategory\SubCategoryResource;
use App\Http\Resources\Users\Profile\UserListResource;
use App\Http\Resources\Users\Profile\UserSugResource;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResouce extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logo = MediaResource::make($this->getFirstMedia(MediaCollection::DOCTOR_LOGO_COLLECTION));

        $data = [
            'id' => $this->id, 
            'clinic_name' => $this->clinic_name,
            'address_text' => $this->address_text,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'license_number' => $this->license_number,
            'is_center' => $this->is_center,
            'bio' => $this->bio,
            'rate' => $this->total_rate,
            'logo' =>  $logo->resource == null 
                    ? DefaultMediaResource::make(1)
                    : $logo
        ];

        $data['number_of_favorites'] = $this->favorites()->count();
        $data['sub_categories']   = $this->whenLoaded('subCategories');
        $data['user']   = $this->whenLoaded('user');
        $data['complaints'] = ComplaintResource::collection($this->whenLoaded('complaints'));

        if(auth()->check() && auth()->user()->isPatient())
        {
            $data['is_favorite'] = $this->favorites()->where('user_id' , auth()->id())->exists();
        }

        $routeName = $request->route()->getName();

        switch ($routeName)
        {
            case RouteNames::DOCTORS_FILTER_USER_SIDE:
                $data['sub_categories']   = $this->whenLoaded('subCategories');
                $data['shifts']   = ShiftResource::collection($this->whenLoaded('shifts'));
                $data['licenses'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_CERTIFICATES_COLLECTION));
                // $data['cover'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_COVER_COLLECTION));
                // $data['logo'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_LOGO_COLLECTION));
            break;
            case in_array($routeName, [
                RouteNames::PATIENT_RELATIONS,
                RouteNames::PATIENT_RESERVATIONS,
                RouteNames::RESERVATION_DETAILS,
                RouteNames::ARTICLES_SHOW,
                RouteNames::ADMIN_RESERVATIONS,
                ]):
                $data['sub_categories']   = $this->whenLoaded('subCategories');
                // $data['cover'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_COVER_COLLECTION));
                // $data['logo'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_LOGO_COLLECTION));
            break;
            case RouteNames::DOCTORS_GET_PROFILE:
                $data['sub_categories']   = SubCategoryResource::collection($this->whenLoaded('subCategories'));
                $data['licenses'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_CERTIFICATES_COLLECTION));
                $data['user'] = UserListResource::make($this->whenLoaded('user'));
                // $data['cover'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_COVER_COLLECTION));
                // $data['logo'] = MediaResource::collection($this->getMedia(MediaCollection::DOCTOR_LOGO_COLLECTION));
                $data['shifts']   = ShiftResource::collection($this->whenLoaded('shifts'));
                $data['rates']   = RateResource::collection($this->whenLoaded('rates'));
                $data['articles']   = ArticleResource::collection($this->whenLoaded('articles'));
            break;
        }

        return $data;
    }
}
