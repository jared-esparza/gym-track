<?php
declare(strict_types=1);

// Explicit test-server connection only; never reads the application's .env.
$dsn = getenv('MIGRATION_TEST_DSN');
if (!$dsn) { fwrite(STDERR, "Set MIGRATION_TEST_DSN, MIGRATION_TEST_USER and MIGRATION_TEST_PASSWORD to an isolated MySQL/MariaDB server.\n"); exit(1); }
$pdo = new PDO($dsn, getenv('MIGRATION_TEST_USER') ?: 'root', getenv('MIGRATION_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$name = 'gym_tracker_test_' . bin2hex(random_bytes(6));
$pdo->exec("CREATE DATABASE {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE {$name}");
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function runSql(PDO $pdo, string $sql): void {
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }
}
try {
    // Reconstruct the previous constraints from the new initial schema.
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $legacy = preg_replace('/CREATE TABLE workout_exercises \([\s\S]*?;\s*/', '', $schema);
    $legacy = str_replace('muscle_group_id TINYINT UNSIGNED NULL,', 'muscle_group_id TINYINT UNSIGNED NOT NULL,', $legacy);
    $legacy = preg_replace('/  muscle_group_key[^\n]+\n/', '', $legacy);
    $legacy = str_replace('(user_id, muscle_group_key, name)', '(user_id, muscle_group_id, name)', $legacy);
    $legacy = str_replace('workout_id INT UNSIGNED NULL,', 'workout_id INT UNSIGNED NOT NULL,', $legacy);
    $legacy = str_replace('REFERENCES workouts(id) ON DELETE SET NULL', 'REFERENCES workouts(id)', $legacy);
    runSql($pdo, $legacy);
    $pdo->exec("INSERT INTO users (email,password_hash) VALUES ('one@example.test','test'),('two@example.test','test')");
    $pdo->exec("INSERT INTO workouts (user_id,name) VALUES (1,'Piernas'),(2,'Piernas')");
    $pdo->exec('INSERT INTO workout_muscle_groups VALUES (1,4),(2,4)');
    $pdo->exec("INSERT INTO exercises (user_id,muscle_group_id,name,metric_type) VALUES (1,4,'Prensa','kg'),(2,4,'Prensa','kg')");
    $pdo->exec("INSERT INTO records (user_id,exercise_id,workout_id,value,metric_type) VALUES (1,1,1,40,'kg')");
    runSql($pdo, file_get_contents(__DIR__ . '/../database/migrations/2026-10-19-flexible-training.sql'));
    check((int) $pdo->query('SELECT COUNT(*) FROM workout_exercises')->fetchColumn() === 2, 'Migration did not preserve selection');
    check((int) $pdo->query('SELECT COUNT(*) FROM workout_exercises we JOIN workouts w ON w.id=we.workout_id JOIN exercises e ON e.id=we.exercise_id WHERE w.user_id <> e.user_id')->fetchColumn() === 0, 'Migration crossed owners');
    $pdo->exec("INSERT INTO exercises (user_id,name,metric_type) VALUES (1,'Libre','kg')");
    try {
        $pdo->exec("INSERT INTO exercises (user_id,name,metric_type) VALUES (1,'Libre','kg')");
        throw new RuntimeException('Duplicate unclassified exercise allowed');
    } catch (PDOException $e) { check((int) $e->errorInfo[1] === 1062, 'Unexpected duplicate error'); }
    $pdo->exec("INSERT INTO records (user_id,exercise_id,value,metric_type) VALUES (1,3,20,'kg')");
    $pdo->exec('UPDATE exercises SET muscle_group_id = NULL WHERE id=1');
    check((int) $pdo->query('SELECT COUNT(*) FROM workout_exercises WHERE exercise_id=1')->fetchColumn() === 1, 'Group change altered membership');
    $pdo->exec('DELETE FROM workouts WHERE id=1');
    check((int) $pdo->query('SELECT COUNT(*) FROM records WHERE workout_id IS NULL')->fetchColumn() === 2, 'Workout deletion lost history');
    echo "Migration: preserved membership/ownership/history, nullable fields and duplicate protection passed.\n";
    // Also validate a completely fresh installation in a separate generated database.
    $fresh = $name . '_fresh';
    $pdo->exec("CREATE DATABASE {$fresh} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try { $pdo->exec("USE {$fresh}"); runSql($pdo, $schema); echo "Fresh schema: passed.\n"; }
    finally { $pdo->exec("DROP DATABASE {$fresh}"); }
} finally {
    $pdo->exec("DROP DATABASE {$name}");
}
