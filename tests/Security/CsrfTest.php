<?php

declare(strict_types=1);

namespace GymTracker\Tests\Security;

use GymTracker\Security;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsStableInsideSession(): void
    {
        $session = [];

        $first = Security::csrfToken($session);
        $second = Security::csrfToken($session);

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
    }

    public function testValidationRejectsMissingOrInvalidToken(): void
    {
        $session = [];
        $token = Security::csrfToken($session);

        self::assertTrue(Security::validCsrfToken($session, $token));
        self::assertFalse(Security::validCsrfToken($session, ''));
        self::assertFalse(Security::validCsrfToken($session, str_repeat('0', 64)));
    }
}
