<?php

declare(strict_types=1);

namespace GymTracker\Tests\Auth;

use GymTracker\RegistrationService;
use GymTracker\RegistrationException;
use PDO;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email_verified_at DATETIME NULL,
            verification_token_hash CHAR(64) NULL,
            verification_expires_at DATETIME NULL
        )');
    }

    public function testResendsVerificationForExistingUnverifiedUser(): void
    {
        $this->pdo->prepare('INSERT INTO users (email, password_hash, verification_token_hash, verification_expires_at) VALUES (?, ?, ?, datetime("now", "+1 day"))')
            ->execute(['pending@example.com', password_hash('old-password', PASSWORD_DEFAULT), str_repeat('a', 64)]);

        $sent = [];
        $result = RegistrationService::register(
            $this->pdo,
            'pending@example.com',
            'new-password',
            function (array $user, string $token) use (&$sent): void {
                $sent[] = [$user, $token];
            }
        );

        self::assertSame('Cuenta creada. Revisa tu email para verificarla.', $result['message']);
        self::assertCount(1, $sent);

        $row = $this->pdo->query('SELECT verification_token_hash FROM users WHERE email = "pending@example.com"')->fetch(PDO::FETCH_ASSOC);
        self::assertNotSame(str_repeat('a', 64), $row['verification_token_hash']);
    }

    public function testRollsBackNewUserWhenVerificationEmailFails(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('No se pudo enviar el email de verificación. Revisa la configuración SMTP.');

        try {
            RegistrationService::register(
                $this->pdo,
                'new@example.com',
                'new-password',
                static function (): void {
                    throw new \RuntimeException('SMTP failed');
                }
            );
        } finally {
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE email = "new@example.com"')->fetchColumn();
            self::assertSame(0, $count);
        }
    }
}
