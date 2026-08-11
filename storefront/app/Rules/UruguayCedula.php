<?php
declare(strict_types=1);
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
final class UruguayCedula implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (! in_array(strlen($digits), [7, 8], true)) { $fail('La cédula debe tener un formato uruguayo válido.'); return; }
        if (strlen($digits) === 7) $digits = '0'.$digits;
        $body = substr($digits, 0, 7); $check = (int) substr($digits, -1); $sum = 0;
        foreach ([2, 9, 8, 7, 6, 3, 4] as $index => $weight) $sum += (int) $body[$index] * $weight;
        if ((10 - ($sum % 10)) % 10 !== $check) $fail('La cédula no supera el dígito verificador.');
    }
}
