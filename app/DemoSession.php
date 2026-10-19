<?php
declare(strict_types=1);
namespace GymTracker;

use PDO;

/** Ephemeral SQLite projection of the data stored in one PHP session. */
final class DemoSession
{
    private const TABLES = ['users', 'muscle_groups', 'gyms', 'workouts', 'exercises', 'workout_exercises', 'exercise_gyms', 'records', 'rate_limits', 'sqlite_sequence'];

    public static function start(array &$session): void
    {
        unset($session['demo_data'], $session['import_preview']);
        $session['demo'] = bin2hex(random_bytes(16));
    }

    public static function connect(array &$session): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec(self::schema());
        if (isset($session['demo_data'])) {
            foreach (self::TABLES as $table) {
                if ($table === 'sqlite_sequence') $pdo->exec('DELETE FROM sqlite_sequence');
                foreach ($session['demo_data'][$table] ?? [] as $row) {
                    $columns = implode(',', array_keys($row));
                    $placeholders = implode(',', array_fill(0, count($row), '?'));
                    $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute(array_values($row));
                }
            }
        } else {
            self::seed($pdo);
        }
        return $pdo;
    }

    public static function save(array &$session, PDO $pdo): void
    {
        // Failed requests must never persist an incomplete transaction.
        if ($pdo->inTransaction()) $pdo->rollBack();
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $snapshot[$table] = $pdo->query("SELECT * FROM {$table}")->fetchAll();
        }
        // SQLite populated these counters while restoring the other tables.
        $session['demo_data'] = $snapshot;
    }

    private static function schema(): string
    {
        return <<<'SQL'
CREATE TABLE users (id INTEGER PRIMARY KEY, gyms_enabled INTEGER NOT NULL DEFAULT 0);
CREATE TABLE muscle_groups (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, sort_order INTEGER);
CREATE TABLE workouts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, name TEXT NOT NULL COLLATE NOCASE, UNIQUE(user_id,name));
CREATE TABLE gyms (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, name TEXT NOT NULL COLLATE NOCASE, UNIQUE(user_id,name));
CREATE TABLE exercises (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, muscle_group_id INTEGER REFERENCES muscle_groups(id), name TEXT NOT NULL COLLATE NOCASE, metric_type TEXT NOT NULL, notes TEXT);
CREATE UNIQUE INDEX unique_exercise ON exercises(user_id, COALESCE(muscle_group_id,0), name);
CREATE TABLE workout_exercises (workout_id INTEGER REFERENCES workouts(id) ON DELETE CASCADE, exercise_id INTEGER REFERENCES exercises(id) ON DELETE CASCADE, PRIMARY KEY(workout_id,exercise_id));
CREATE TABLE exercise_gyms (exercise_id INTEGER REFERENCES exercises(id) ON DELETE CASCADE, gym_id INTEGER REFERENCES gyms(id) ON DELETE CASCADE, PRIMARY KEY(exercise_id,gym_id));
CREATE TABLE records (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, exercise_id INTEGER NOT NULL REFERENCES exercises(id) ON DELETE CASCADE, workout_id INTEGER REFERENCES workouts(id) ON DELETE SET NULL, gym_id INTEGER REFERENCES gyms(id) ON DELETE SET NULL, value NUMERIC NOT NULL, metric_type TEXT NOT NULL, note TEXT, recorded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE rate_limits (action TEXT, identifier TEXT, attempts INTEGER NOT NULL DEFAULT 0, window_started_at INTEGER NOT NULL, PRIMARY KEY(action,identifier));
SQL;
    }

    private static function seed(PDO $pdo): void
    {
        $pdo->exec('INSERT INTO users (id) VALUES (1)');
        $names = ['Pectoral', 'Hombro', 'Tríceps', 'Piernas Cuádriceps', 'Gemelos', 'Lumbar', 'Espalda', 'Bíceps', 'Antebrazo', 'Piernas Femoral', 'Glúteos', 'Abdominales', 'Cardio'];
        $stmt = $pdo->prepare('INSERT INTO muscle_groups VALUES (?, ?, ?)');
        foreach ($names as $i => $name) $stmt->execute([$i + 1, $name, $i + 1]);
        $pdo->exec("INSERT INTO workouts (user_id,name) VALUES (1,'Piernas'), (1,'Torso')");
        $stmt = $pdo->prepare('INSERT INTO exercises (user_id,muscle_group_id,name,metric_type,notes) VALUES (1,?,?,?,?)');
        foreach ([[4,'Sentadilla','kg','Bajada controlada.'], [4,'Prensa','kg',''], [1,'Press banca','kg','Pausa breve en el pecho.'], [7,'Remo','kg',''], [null,'Máquina nueva','kg','Ejercicio libre, sin rutina.'], [13,'Cinta','km','']] as $row) $stmt->execute($row);
        $pdo->exec('INSERT INTO workout_exercises VALUES (1,1), (1,2), (2,3), (2,4)');
        $stmt = $pdo->prepare('INSERT INTO records (user_id,exercise_id,workout_id,value,metric_type,note,recorded_at) VALUES (1,?,?,?,?,?,?)');
        for ($week = 5; $week >= 0; $week--) {
            foreach ([1,2,3,4,5,6] as $exercise) {
                $value = $exercise === 6 ? 2 + (5 - $week) * 0.3 : 20 + $exercise * 10 + (5 - $week) * 2.5;
                $stmt->execute([$exercise, $exercise <= 2 ? 1 : ($exercise <= 4 ? 2 : null), $value, $exercise === 6 ? 'km' : 'kg', '', date('Y-m-d H:i:s', strtotime("-{$week} weeks"))]);
            }
        }
    }
}
