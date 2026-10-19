<?php
declare(strict_types=1);
namespace GymTracker\Tests\Demo;

use GymTracker\DemoSession;
use PHPUnit\Framework\TestCase;

final class DemoSessionTest extends TestCase
{
    public function testSeedHasIndependentListsAndFreeRecordsAndCanBeRestored(): void
    {
        $session = [];
        DemoSession::start($session);
        $pdo = DemoSession::connect($session);
        self::assertGreaterThan(0, $pdo->query('SELECT COUNT(*) FROM records WHERE workout_id IS NULL')->fetchColumn());
        self::assertGreaterThan(0, $pdo->query('SELECT COUNT(*) FROM exercises WHERE muscle_group_id IS NULL')->fetchColumn());
        $pdo->exec("INSERT INTO exercises (user_id, name, metric_type) VALUES (1, 'Personal', 'kg')");
        DemoSession::save($session, $pdo);
        $restored = DemoSession::connect($session);
        self::assertSame(1, (int) $restored->query("SELECT COUNT(*) FROM exercises WHERE name = 'Personal'")->fetchColumn());
        $other = [];
        DemoSession::start($other);
        self::assertSame(0, (int) DemoSession::connect($other)->query("SELECT COUNT(*) FROM exercises WHERE name = 'Personal'")->fetchColumn());
        DemoSession::start($session);
        self::assertSame(0, (int) DemoSession::connect($session)->query("SELECT COUNT(*) FROM exercises WHERE name = 'Personal'")->fetchColumn());
    }
}
