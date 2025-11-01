<?php

namespace App\Models;

use Carbon\Carbon;
use App\Enums\GenderEnum;
use App\Constants\Resources;
use Spatie\MediaLibrary\HasMedia;
use App\Constants\MediaCollection;
use App\Models\Scopes\DoctorLoadingScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Doctor extends Model implements HasMedia
{
    use HasFactory , InteractsWithMedia;
    protected $guarded = ['id'];

    protected static function booted()
    {
        static::addGlobalScope(new DoctorLoadingScope);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \App\Models\Doctor
     */
    public static function findByIdOrFail($id, $with = [], $withTrashed = false, $selectedColumns = null)
    {
        return findByIdOrFail(
            self::class,
            $id,
            GenderEnum::MALE,
            Resources::RES_DOCTOR,
            $with,
            $withTrashed,
            $selectedColumns
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::DOCTOR_CERTIFICATES_COLLECTION);
        $this->addMediaCollection(MediaCollection::DOCTOR_COVER_COLLECTION)
                ->singleFile();
        $this->addMediaCollection(MediaCollection::DOCTOR_LOGO_COLLECTION)
                ->singleFile();
    }

    public function delete()
    {
        deleteFilesFromMedia($this , MediaCollection::ARTICLE_COLLECTION);

        return parent::delete();
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function rates()
    {
        return $this->hasMany(Rate::class);
    }
    
    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function phoneNumbers()
    {
        return $this->hasMany(DoctorPhoneNumber::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'subscriptions')
                    ->using(Subscription::class)
                    ->withPivot(
                        'original_price',
                        'price_after_discount',
                         'discount_percentage',
                         'start_at' ,
                         'end_at',
                         'number_of_days',
                         'is_active'
                    )
                    ->withTimestamps();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function subCategories()
    {
        return $this->belongsToMany(SubCategory::class, 'specializations')
                    ->using(Specialization::class)
                    ->withTimestamps();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function addedMedicines()
    {
        return $this->morphMany(Medicine::class, 'userable');
    }

    public function addedInstructions()
    {
        return $this->morphMany(Instruction::class, 'userable');
    }


    public function scopeFilter($query , $data)
    {
        return $query

        ->when(isset($data['name']) , function($query) use ($data) {
            $query->whereHas('user', function($query) use ($data) {
                $query->where('name', 'like', '%' . $data['name'] . '%');
            })
            ->orWhere('clinic_name', 'like', '%' . $data['name'] . '%')
            ->orWhereHas('subCategories', function($query) use ($data) {
                $query->where('name', 'like', '%' . $data['name'] . '%')
                ->orWhereHas('category', function($query) use ($data) {
                    $query->where('name', 'like', '%' . $data['name'] . '%');
                });
            })
        })

        ->when(isset($data['address_text']) , function($query) use ($data) {
            $query->where('address_text', 'like', '%' . $data['address_text'] . '%');
        })

        ->when(isset($data['rate']) , function($query) use ($data) {
            $query->whereBetween('total_rate', [$data['rate'] - 1, $data['rate'] + 1]);
        })

        ->when(isset($data['lat']) && isset($data['lng']) , function($query) use ($data) {
            $lat = $data['lat'];
            $lng = $data['lng'];
            $radius = 4; // Radius in kilometers

            // Haversine formula to calculate distance
            $query->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance", [$lat, $lng, $lat])
                ->having('distance', '<=', $radius);
        })

        ->when(isset($data['phone_number']) , function($query) use ($data) {
            $query->whereHas('user', function($query) use ($data) {
                $query->where('phone_number', 'like', '%' . $data['phone_number'] . '%');
            })
            ->orWhereHas('phoneNumbers', function($query) use ($data) {
                $query->where('phone_number', 'like', '%' . $data['phone_number'] . '%');
            });
        })

        ->when(isset($data['sub_category_ids']) , function($query) use ($data) {
            $query->whereHas('subCategories', function($query) use ($data) {
                $query->whereIn('sub_categories.id', $data['sub_category_ids']);
            });
        })

        ->when(isset($data['category_ids']) , function($query) use ($data) {
            $query->whereHas('subCategories.category', function($query) use ($data) {
                $query->whereIn('categories.id', $data['category_ids']);
            });
        })

        ->when(isset($data['is_center']) , function($query) use ($data) {
            $query->where('is_center' , $data['is_center']);
        })

        ->when(isset($data['city_ids']), function($query) use ($data) {
            $query->whereHas('user', function($query) use ($data) {
                $query->whereIn('city_id', $data['city_ids']);
            });
        });


    }

    public function scopeNotBanned($query)
    {
        return 
        $query->whereHas('user', function($query) {
            $query->whereHas('profile', function($query) {
                $query->whereNull('banned_until')
                    ->orWhere('banned_until', '<', now());
            });
        });
    }

    public function scopeSubscriped($query)
    {
        return 
        $query->whereHas('plans' , function($query) {
            $query->where('is_active' , 1);
        });
    }
}
