<?php

namespace App\Support\Ui;

/**
 * Ties a form control to its label, hint and error by the input name
 * (`contacts[0][email]` → id `f-contacts-0-email`, error key `contacts.0.email`).
 */
final class Field
{
    public static function id(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        return 'f-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    }

    public static function errorKey(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        return rtrim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }
}
