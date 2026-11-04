<?php

namespace App\Rules;

use App\Support\ParcelBoundary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidParcelBoundary implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && mb_strlen($value) > 200000) {
            $fail('Le contour est trop volumineux.');

            return;
        }

        if (ParcelBoundary::normalize($value) === null) {
            $fail('Le contour doit être un polygone GeoJSON valide avec au moins trois points distincts et une surface non nulle.');
        }
    }
}
