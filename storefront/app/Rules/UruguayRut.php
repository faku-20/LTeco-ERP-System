<?php
declare(strict_types=1);
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
final class UruguayRut implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (strlen($digits) !== 12) { $fail('El RUT debe tener 12 dígitos.'); return; }
        $sum = 0; foreach ([4,3,2,9,8,7,6,5,4,3,2] as $index => $weight) $sum += (int) $digits[$index] * $weight;
        if ((10 - ($sum % 10)) % 10 !== (int) $digits[11]) $fail('El RUT no supera el dígito verificador.');
    }
}
