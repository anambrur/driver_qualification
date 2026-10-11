<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * NANP (+1) area codes of countries other than the US (and its territories) and Canada.
     * SMS to them is billed as international and they are common toll-fraud targets, so they
     * are refused like the premium-rate codes 900 and 976 (PO-11).
     */
    private const BLOCKED_NANP_AREA_CODES = [
        '900', '976', // US premium rate
        '242', // Bahamas
        '246', // Barbados
        '264', // Anguilla
        '268', // Antigua and Barbuda
        '284', // British Virgin Islands
        '345', // Cayman Islands
        '441', // Bermuda
        '473', // Grenada
        '649', // Turks and Caicos Islands
        '658', '876', // Jamaica
        '664', // Montserrat
        '721', // Sint Maarten
        '758', // Saint Lucia
        '767', // Dominica
        '784', // Saint Vincent and the Grenadines
        '809', '829', '849', // Dominican Republic
        '868', // Trinidad and Tobago
        '869', // Saint Kitts and Nevis
    ];

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
                return $this->isValidNanpNationalNumber($cleaned)
                    && ! $this->isBlockedNanpAreaCode($cleaned);
            }

            if (strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
                return $this->isValidNanpNationalNumber(substr($cleaned, 1))
                    && ! $this->isBlockedNanpAreaCode(substr($cleaned, 1));
            }

            $isTestCountryNumber = (strlen($cleaned) === 11 && str_starts_with($cleaned, '01'))
                || (strlen($cleaned) === 13 && str_starts_with($cleaned, '880'))
                || (strlen($cleaned) === 12 && str_starts_with($cleaned, '91'));

            if (! $isTestCountryNumber || ! $this->allowsTestCountries()) {
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
                && $this->isValidNanpNationalNumber(substr($digits, 1))
                && ! $this->isBlockedNanpAreaCode(substr($digits, 1));
        }

        // Bangladesh and India are only for internal testing (PO-11)
        if (str_starts_with($digits, '880')) {
            return $this->allowsTestCountries() && (bool) preg_match('/^8801[3-9]\d{8}$/', $digits);
        }

        if (str_starts_with($digits, '91')) {
            return $this->allowsTestCountries() && (bool) preg_match('/^91[6-9]\d{9}$/', $digits);
        }

        // Any other destination is refused: SMS to unvalidated countries enables toll fraud.
        return false;
    }

    /**
     * Production sends SMS to the US and Canada only; +880 (Bangladesh) and +91 (India) are
     * accepted on local and testing environments for the team's own phones (PO-11).
     */
    private function allowsTestCountries(): bool
    {
        return app()->environment('local', 'testing');
    }

    private function isBlockedNanpAreaCode(string $digits): bool
    {
        return in_array(substr($digits, 0, 3), self::BLOCKED_NANP_AREA_CODES, true);
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
