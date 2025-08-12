<?php

namespace App\Http\Controllers\Patient;

use App\Constants\ApiMessages;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetItemsRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\SubCategory\SubCategoryResource;
use App\Http\Resources\System\Info\CityResource;
use App\Services\Base\ListService;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function __construct(
        protected ListService $listService
    ){}

    public function categories(GetItemsRequest $request)
    {
        return success(
            $this->listService->categories($request->validated()),
            ApiMessages::MSG_SUCCESS,
            CategoryResource::class,
            $request->has('per_page')
        );
    }

    public function subcategories(GetItemsRequest $request)
    {
        return success(
            $this->listService->subcategories($request->validated()),
            ApiMessages::MSG_SUCCESS,
            SubCategoryResource::class,
            $request->has('per_page')
        );
    }

    public function cities(GetItemsRequest $request)
    {
        return success(
            $this->listService->cities($request->validated()),
            ApiMessages::MSG_SUCCESS,
            CityResource::class,
            $request->has('per_page')
        );
    }

    public function days(GetItemsRequest $request)
    {
        return success(
            $this->listService->days($request->validated()),
            ApiMessages::MSG_SUCCESS,
            CityResource::class,
            $request->has('per_page')
        );
    }
}
