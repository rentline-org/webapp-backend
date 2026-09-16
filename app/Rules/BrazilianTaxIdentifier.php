<?php

namespace App\Rules;

use App\Enums\ContactTaxIdType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BrazilianTaxIdentifier implements ValidationRule
{
    public function __construct(private readonly ?string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';
        $valid = match ($this->type) {
            ContactTaxIdType::CPF->value => $this->isValidCpf($digits),
            ContactTaxIdType::CNPJ->value => $this->isValidCnpj($digits),
            default => false,
        };

        if (! $valid) {
            $fail('The :attribute must be a valid CPF or CNPJ.');
        }
    }

    private function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += ((int) $digits[$index]) * ($position + 1 - $index);
            }

            $checkDigit = ($sum * 10) % 11;
            $checkDigit = $checkDigit === 10 ? 0 : $checkDigit;

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        return $this->cnpjDigit($digits, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) === (int) $digits[12]
            && $this->cnpjDigit($digits, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) === (int) $digits[13];
    }

    /** @param array<int, int> $weights */
    private function cnpjDigit(string $digits, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $digits[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
