<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * Normalize a phone number to E.164 format (e.g. +12092776341).
     */
    public function normalize(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber) ?? '';

        if ($cleaned === '') {
            return '';
        }

        if ($this->isDuplicateUsCountryCode($cleaned)) {
            $cleaned = substr($cleaned, 1);
        }

        if (strlen($cleaned) === 10 && $this->isValidNanpNationalNumber($cleaned)) {
            return '+1'.$cleaned;
        }

        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
            return '+'.$cleaned;
        }

        if (str_starts_with($cleaned, '01') && strlen($cleaned) === 11) {
            return '+880'.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '880') && strlen($cleaned) === 13) {
            return '+'.$cleaned;
        }

        if (str_starts_with($cleaned, '91') && strlen($cleaned) === 12) {
            return '+'.$cleaned;
        }

        return '+'.$cleaned;
    }

    /**
     * Validate that a phone number is in a valid E.164 format.
     */
    public function isValid(string $phoneNumber): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber) ?? '';
        $hasExplicitCountryPrefix = str_starts_with(trim($phoneNumber), '+');

        if (! $hasExplicitCountryPrefix) {
            if (strlen($cleaned) === 10) {
                return $this->isValidNanpNationalNumber($cleaned);
            }

            if (strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
                return $this->isValidNanpNationalNumber(substr($cleaned, 1));
            }

            if (
                ! (strlen($cleaned) === 11 && str_starts_with($cleaned, '01'))
                && ! (strlen($cleaned) === 13 && str_starts_with($cleaned, '880'))
                && ! (strlen($cleaned) === 12 && str_starts_with($cleaned, '91'))
            ) {
                return false;
            }
        }

        $normalized = $this->normalize($phoneNumber);

        if (! preg_match('/^\+[1-9]\d{1,14}$/', $normalized)) {
            return false;
        }

        $length = strlen($normalized);

        if ($length < 10 || $length > 16) {
            return false;
        }

        $digits = substr($normalized, 1);

        if (str_starts_with($digits, '1')) {
            return strlen($digits) === 11
                && $this->isValidNanpNationalNumber(substr($digits, 1));
        }

        if (str_starts_with($digits, '880')) {
            return (bool) preg_match('/^8801[3-9]\d{8}$/', $digits);
        }

        if (str_starts_with($digits, '91')) {
            return (bool) preg_match('/^91[6-9]\d{9}$/', $digits);
        }

        return true;
    }

    private function isDuplicateUsCountryCode(string $digits): bool
    {
        return strlen($digits) === 12
            && str_starts_with($digits, '11')
            && $this->isValidNanpNationalNumber(substr($digits, 2));
    }

    private function isValidNanpNationalNumber(string $digits): bool
    {
        return (bool) preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $digits);
    }
}
