<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Constants\Resources;
use Spatie\MediaLibrary\HasMedia;
use App\Constants\MediaCollection;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reservation extends Model implements HasMedia
{
    use HasFactory , InteractsWithMedia;
    protected $guarded = ['id'];
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::RESERVATION_COLLECTION);
    }

    public function delete()
    {
        deleteFilesFromMedia($this , MediaCollection::ARTICLE_COLLECTION);

        return parent::delete();
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
    
    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function visit()
    {
        return $this->hasOne(Visit::class);
    }
    public function day()
    {
        return $this->belongsTo(Day::class);
    }

    /**
     * @return \App\Models\Reservation
     */
    public static function findByIdOrFail($id, $with = [], $withTrashed = false, $selectedColumns = null)
    {
        return findByIdOrFail(
            self::class,
            $id,
            GenderEnum::MALE,
            Resources::RES_RESERVATION,
            $with,
            $withTrashed,
            $selectedColumns
        );
    }

    public function scopeFilter($query , $data , $doctor_id , $patient_ids)
    {
        return $query
        
        ->when(isset($doctor_id) , function($query) use ($doctor_id) {
            $query->where('doctor_id' , $doctor_id);
        })
        
        ->when(isset($patient_ids) , function($query) use ($patient_ids) {
            $query->whereIn('patient_id' , $patient_ids);
        })

        ->when(isset($data['date']) , function($query) use ($data) {
            $query->whereDate('date' , $data['date']);
        })

        ->when(isset($data['search']) , function($query) use ($data) {
            $query
            ->whereHas('patient' , function($query) use ($data) {
                $query->where('full_name' , 'like' , '%' . $data['search'] . '%');
            })
            ->orWhereHas('doctor' , function($query) use ($data) {
                $query->where('clinic_name' , 'like' , '%' . $data['search'] . '%');
            });
        })
        
        ->when(isset($data['start_time']) , function($query) use ($data) {
            $query->where('time_to_come' , '>=', $data['start_time']);
        })
        ->when(isset($data['end_time']) , function($query) use ($data) {
            $query->where('time_to_come' , '<=', $data['end_time']);
        });
    }

    public function scopeDoctorFilter($query , $data , $doctor_id)
    {
        return $query
            ->where('doctor_id' , $doctor_id)
            ->when(isset($data['search']) , function($query) use ($data) {
                $query->where('text' , 'like' , '%' . $data['search'] . '%')
                ->orWhereHas('patient' , function($query) use ($data) {
                    $query->where('full_name' , 'like' , '%' . $data['search'] . '%');
                });
            });
    }
}