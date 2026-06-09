<?php

declare(strict_types=1);

namespace GymTracker\Tests\Security;

use GymTracker\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        RateLimiter::installSchema($this->pdo);
    }

    public function testAllowsUntilLimitAndBlocksAfterLimit(): void
    {
        self::assertTrue(RateLimiter::attempt($this->pdo, 'login', 'ip:127.0.0.1', 2, 60, 1000));
        self::assertTrue(RateLimiter::attempt($this->pdo, 'login', 'ip:127.0.0.1', 2, 60, 1001));
        self::assertFalse(RateLimiter::attempt($this->pdo, 'login', 'ip:127.0.0.1', 2, 60, 1002));
    }

    public function testSeparatesActionsAndIdentifiers(): void
    {
        RateLimiter::attempt($this->pdo, 'login', 'ip:127.0.0.1', 1, 60, 1000);

        self::assertTrue(RateLimiter::attempt($this->pdo, 'register', 'ip:127.0.0.1', 1, 60, 1001));
        self::assertTrue(RateLimiter::attempt($this->pdo, 'login', 'ip:127.0.0.2', 1, 60, 1001));
        self::assertFalse(RateLimiter::attempt($this->pdo, 'login', 'ip:127.0.0.1', 1, 60, 1001));
    }
}
