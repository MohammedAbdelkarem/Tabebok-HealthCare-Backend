<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

class PhoneNumberRule implements ValidationRule
{
    //TODO:TEMPLATE
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = Validator::make(['number' => $value], [
            'number' => ['string', 'regex:/^\09\d{8}$/'],
        ]);
        if (!$validator->passes()) {
            $fail(__("validation.Invalid phone number"));
        }
    }
}