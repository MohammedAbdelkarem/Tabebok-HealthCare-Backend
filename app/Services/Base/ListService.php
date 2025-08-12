<?php

namespace App\Services\Base;

use App\Models\Category;
use App\Models\Day;
use App\Models\SubCategory;
use App\Models\System\Info\City;

/**
 * Class ListService.
 */
class ListService
{
    public function categories($data)
    {
        return getOrPaginate(
            Category::with('subCategories'),
            $data
        );
    }
    public function subcategories($data)
    {
        return getOrPaginate(
            SubCategory::query(),
            $data
        );
    }
    public function cities($data)
    {
        return getOrPaginate(
            City::query(),
            $data
        );
    }
    public function days($data)
    {
        return getOrPaginate(
            Day::query(),
            $data
        );
    }
}
