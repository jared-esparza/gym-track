<?php

declare(strict_types=1);

namespace GymTracker;

use PDO;
use Throwable;

final class RegistrationService
{
    /**
     * @param callable(array{email: string}, string): void $sendVerification
     * @return array{message: string}
     */
    public static function register(PDO $pdo, string $email, string $password, callable $sendVerification): array
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT email_verified_at FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && $existing['email_verified_at']) {
                throw new RegistrationException('Ya existe una cuenta con ese email', 409);
            }

            if ($existing) {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, verification_token_hash = ?, verification_expires_at = ' . self::expirySql($pdo) . ' WHERE email = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $tokenHash, $email]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, verification_token_hash, verification_expires_at) VALUES (?, ?, ?, ' . self::expirySql($pdo) . ')');
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $tokenHash]);
            }

            try {
                $sendVerification(['email' => $email], $token);
            } catch (Throwable) {
                throw new RegistrationException('No se pudo enviar el email de verificación. Revisa la configuración SMTP.', 500);
            }

            $pdo->commit();

            return ['message' => 'Cuenta creada. Revisa tu email para verificarla.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function expirySql(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'DATE_ADD(NOW(), INTERVAL 24 HOUR)'
            : 'datetime("now", "+1 day")';
    }
}
