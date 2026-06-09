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

        if (str_starts_with($cleaned, '01') && strlen($cleaned) === 11) {
            return '+880'.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '880') && strlen($cleaned) === 13) {
            return '+880'.substr($cleaned, 3);
        }

        if (str_starts_with($cleaned, '91') && strlen($cleaned) === 12) {
            return '+91'.substr($cleaned, 2);
        }

        if (str_starts_with($cleaned, '1') && strlen($cleaned) === 11) {
            return '+1'.substr($cleaned, 1);
        }

        if (strlen($cleaned) === 10) {
            return '+1'.$cleaned;
        }

        if (str_starts_with($phoneNumber, '+')) {
            return '+'.$cleaned;
        }

        return '+'.$cleaned;
    }

    /**
     * Validate that a phone number is in a valid E.164 format.
     */
    public function isValid(string $phoneNumber): bool
    {
        $normalized = $this->normalize($phoneNumber);

        if (! preg_match('/^\+[1-9]\d{1,14}$/', $normalized)) {
            return false;
        }

        $length = strlen($normalized);

        return $length >= 10 && $length <= 16;
    }
}
