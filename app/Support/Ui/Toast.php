<?php

namespace App\Support\Ui;

/**
 * Toasts for the next page: flashed to the session, shown by x-ui.toaster via window.notify().
 */
final class Toast
{
    public const SESSION_KEY = 'toasts';

    public static function success(string $message): void
    {
        self::push('success', $message);
    }

    public static function error(string $message): void
    {
        self::push('error', $message);
    }

    public static function warning(string $message): void
    {
        self::push('warning', $message);
    }

    public static function info(string $message): void
    {
        self::push('info', $message);
    }

    private static function push(string $category, string $message): void
    {
        $toasts = session(self::SESSION_KEY, []);
        $toasts[] = ['category' => $category, 'message' => $message];

        session()->flash(self::SESSION_KEY, $toasts);
    }
}
