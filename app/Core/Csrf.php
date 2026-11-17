<?php

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        self::startSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function validate(mixed $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        self::startSession();
        $sessionToken = (string) ($_SESSION[self::SESSION_KEY] ?? '');

        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    private static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
