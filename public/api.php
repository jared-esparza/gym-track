<?php

declare(strict_types=1);

use GymTracker\Auth;
use GymTracker\Config;
use GymTracker\Database;
use GymTracker\Mailer;
use GymTracker\Request;
use GymTracker\Response;

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    match ($action) {
        'me' => me(),
        'register' => postOnly($method) && register(),
        'login' => postOnly($method) && login(),
        'logout' => postOnly($method) && logout(),
        'verify-email' => verifyEmail(),
        'forgot-password' => postOnly($method) && forgotPassword(),
        'reset-password' => postOnly($method) && resetPassword(),
        'bootstrap' => bootstrapData(),
        'workouts' => workouts($method),
        'workout' => workout($method),
        'exercises' => exercises(),
        'exercise' => exercise($method),
        'exercise-summary' => exerciseSummary(),
        'records' => records($method),
        'history' => history(),
        default => Response::error('Acción no encontrada', 404),
    };
} catch (Throwable $e) {
    $isDev = Config::get('APP_ENV', 'production') === 'local';
    Response::error($isDev ? $e->getMessage() : 'Error interno', 500);
}

function postOnly(string $method): bool
{
    if ($method !== 'POST') {
        Response::error('Método no permitido', 405);
    }

    return true;
}

function intParam(array $data, string $key): int
{
    $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
    if (!$value || $value < 1) {
        Response::error("Campo inválido: {$key}");
    }

    return (int) $value;
}

function textParam(array $data, string $key, int $max = 255, bool $required = true): ?string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        if ($required) {
            Response::error("Campo requerido: {$key}");
        }
        return null;
    }

    return mb_substr($value, 0, $max);
}

function metricParam(array $data): string
{
    $metric = (string) ($data['metric_type'] ?? '');
    if (!in_array($metric, ['kg', 'reps', 'min', 'km'], true)) {
        Response::error('Tipo de marca inválido');
    }

    return $metric;
}

function token(): string
{
    return bin2hex(random_bytes(32));
}

function tokenHash(string $token): string
{
    return hash('sha256', $token);
}

function appUrl(string $path): string
{
    return rtrim(GymTracker\Config::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
}

function sendVerification(array $user, string $token): void
{
    $url = appUrl('api.php?action=verify-email&token=' . urlencode($token));
    Mailer::send($user['email'], 'Verifica tu cuenta de Gym Tracker', "Verifica tu cuenta abriendo este enlace:\n{$url}");
}

function me(): never
{
    $user = Auth::user();
    if (!$user) {
        Response::json(['ok' => true, 'user' => null]);
    }

    Response::json(['ok' => true, 'user' => $user]);
}

function register(): never
{
    $data = Request::input();
    $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($data['password'] ?? '');
    if (!$email || strlen($password) < 8) {
        Response::error('Email válido y contraseña de 8 caracteres mínimo requeridos');
    }

    $token = token();
    $pdo = Database::pdo();
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, verification_token_hash, verification_expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), tokenHash($token)]);
    sendVerification(['email' => $email], $token);

    Response::json(['ok' => true, 'message' => 'Cuenta creada. Revisa tu email para verificarla.']);
}

function login(): never
{
    $data = Request::input();
    $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($data['password'] ?? '');
    $stmt = Database::pdo()->prepare('SELECT id, password_hash, email_verified_at FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        Response::error('Credenciales incorrectas', 401);
    }
    if (!$user['email_verified_at']) {
        Response::error('Verifica tu email antes de iniciar sesión', 403);
    }

    Auth::login((int) $user['id']);
    Response::json(['ok' => true]);
}

function logout(): never
{
    Auth::logout();
    Response::json(['ok' => true]);
}

function verifyEmail(): never
{
    $token = (string) ($_GET['token'] ?? '');
    if ($token === '') {
        Response::error('Token requerido');
    }

    $stmt = Database::pdo()->prepare('UPDATE users SET email_verified_at = NOW(), verification_token_hash = NULL, verification_expires_at = NULL WHERE verification_token_hash = ? AND verification_expires_at > NOW()');
    $stmt->execute([tokenHash($token)]);
    if ($stmt->rowCount() < 1) {
        Response::error('Token inválido o caducado', 400);
    }

    header('Location: index.html?verified=1');
    exit;
}

function forgotPassword(): never
{
    $data = Request::input();
    $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if ($email) {
        $token = token();
        $stmt = Database::pdo()->prepare('UPDATE users SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?');
        $stmt->execute([tokenHash($token), $email]);
        if ($stmt->rowCount() > 0) {
            $url = appUrl('index.html?reset=' . urlencode($token));
            Mailer::send($email, 'Restablecer contraseña de Gym Tracker', "Restablece tu contraseña abriendo este enlace:\n{$url}");
        }
    }

    Response::json(['ok' => true, 'message' => 'Si existe una cuenta, recibirás un email.']);
}

function resetPassword(): never
{
    $data = Request::input();
    $token = (string) ($data['token'] ?? '');
    $password = (string) ($data['password'] ?? '');
    if ($token === '' || strlen($password) < 8) {
        Response::error('Token y contraseña válida requeridos');
    }

    $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL WHERE reset_token_hash = ? AND reset_expires_at > NOW()');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), tokenHash($token)]);
    if ($stmt->rowCount() < 1) {
        Response::error('Token inválido o caducado', 400);
    }

    Response::json(['ok' => true]);
}

function bootstrapData(): never
{
    $user = Auth::requireUser();
    $pdo = Database::pdo();
    $groups = $pdo->query('SELECT id, name FROM muscle_groups ORDER BY sort_order')->fetchAll();
    $stmt = $pdo->prepare('SELECT id, name FROM workouts WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user['id']]);

    Response::json(['ok' => true, 'muscle_groups' => $groups, 'workouts' => $stmt->fetchAll()]);
}

function workouts(string $method): never
{
    $user = Auth::requireUser();
    $pdo = Database::pdo();
    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, name FROM workouts WHERE user_id = ? ORDER BY name');
        $stmt->execute([$user['id']]);
        Response::json(['ok' => true, 'workouts' => $stmt->fetchAll()]);
    }
    if ($method !== 'POST') {
        Response::error('Método no permitido', 405);
    }

    $data = Request::input();
    $name = textParam($data, 'name', 120);
    $groupIds = array_values(array_unique(array_map('intval', $data['muscle_group_ids'] ?? [])));
    if (!$groupIds) {
        Response::error('Selecciona al menos un grupo muscular');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO workouts (user_id, name) VALUES (?, ?)');
    $stmt->execute([$user['id'], $name]);
    $workoutId = (int) $pdo->lastInsertId();
    syncWorkoutGroups($workoutId, $groupIds);
    $pdo->commit();

    Response::json(['ok' => true, 'id' => $workoutId]);
}

function workout(string $method): never
{
    $user = Auth::requireUser();
    $data = Request::input() + $_GET;
    $id = intParam($data, 'id');
    assertWorkoutOwner($id, (int) $user['id']);
    $pdo = Database::pdo();

    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, name FROM workouts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        $workout = $stmt->fetch();
        $stmt = $pdo->prepare('SELECT muscle_group_id FROM workout_muscle_groups WHERE workout_id = ?');
        $stmt->execute([$id]);
        Response::json(['ok' => true, 'workout' => $workout, 'muscle_group_ids' => array_map('intval', array_column($stmt->fetchAll(), 'muscle_group_id'))]);
    }

    if ($method === 'POST') {
        $name = textParam($data, 'name', 120);
        $groupIds = array_values(array_unique(array_map('intval', $data['muscle_group_ids'] ?? [])));
        if (!$groupIds) {
            Response::error('Selecciona al menos un grupo muscular');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE workouts SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $id, $user['id']]);
        syncWorkoutGroups($id, $groupIds);
        $pdo->commit();
        Response::json(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM records WHERE workout_id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            Response::error('No se puede eliminar un entrenamiento con registros asociados', 409);
        }

        $stmt = $pdo->prepare('DELETE FROM workouts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        Response::json(['ok' => true]);
    }

    Response::error('Método no permitido', 405);
}

function syncWorkoutGroups(int $workoutId, array $groupIds): void
{
    $pdo = Database::pdo();
    $pdo->prepare('DELETE FROM workout_muscle_groups WHERE workout_id = ?')->execute([$workoutId]);
    $stmt = $pdo->prepare('INSERT INTO workout_muscle_groups (workout_id, muscle_group_id) VALUES (?, ?)');
    foreach ($groupIds as $groupId) {
        if ($groupId > 0) {
            $stmt->execute([$workoutId, $groupId]);
        }
    }
}

function exercises(): never
{
    $user = Auth::requireUser();
    $pdo = Database::pdo();
    $params = [$user['id']];
    $where = 'e.user_id = ?';
    $join = '';

    $groupId = filter_var($_GET['muscle_group_id'] ?? null, FILTER_VALIDATE_INT);
    $workoutId = filter_var($_GET['workout_id'] ?? null, FILTER_VALIDATE_INT);

    if ($groupId && $groupId > 0) {
        $where .= ' AND e.muscle_group_id = ?';
        $params[] = (int) $groupId;
    } elseif ($workoutId && $workoutId > 0) {
        assertWorkoutOwner((int) $workoutId, (int) $user['id']);
        $join = 'JOIN workout_muscle_groups wmg ON wmg.muscle_group_id = e.muscle_group_id AND wmg.workout_id = ?';
        array_unshift($params, (int) $workoutId);
    }

    $stmt = $pdo->prepare("
        SELECT e.id, e.muscle_group_id, e.name, e.metric_type, e.notes, COUNT(r.id) AS record_count
        FROM exercises e
        {$join}
        LEFT JOIN records r ON r.exercise_id = e.id AND r.user_id = e.user_id
        WHERE {$where}
        GROUP BY e.id, e.muscle_group_id, e.name, e.metric_type, e.notes
        ORDER BY e.muscle_group_id, e.name
    ");
    $stmt->execute($params);
    Response::json(['ok' => true, 'exercises' => $stmt->fetchAll()]);
}

function exercise(string $method): never
{
    $user = Auth::requireUser();
    $data = Request::input() + $_GET;
    $pdo = Database::pdo();

    if ($method === 'POST' && empty($data['id'])) {
        $groupId = intParam($data, 'muscle_group_id');
        $name = textParam($data, 'name', 140);
        $metric = metricParam($data);
        $notes = textParam($data, 'notes', 2000, false);
        $stmt = $pdo->prepare('INSERT INTO exercises (user_id, muscle_group_id, name, metric_type, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $groupId, $name, $metric, $notes]);
        Response::json(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    $id = intParam($data, 'id');
    assertExerciseOwner($id, (int) $user['id']);
    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM exercises WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        Response::json(['ok' => true]);
    }

    if ($method === 'POST') {
        if (array_key_exists('name', $data) || array_key_exists('muscle_group_id', $data) || array_key_exists('metric_type', $data)) {
            $groupId = intParam($data, 'muscle_group_id');
            $name = textParam($data, 'name', 140);
            $metric = metricParam($data);
            $notes = textParam($data, 'notes', 2000, false);

            $stmt = $pdo->prepare('SELECT metric_type FROM exercises WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            $currentMetric = (string) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM records WHERE exercise_id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            if ((int) $stmt->fetchColumn() > 0 && $metric !== $currentMetric) {
                Response::error('No se puede cambiar el tipo de marca de un ejercicio con registros guardados', 409);
            }

            $stmt = $pdo->prepare('UPDATE exercises SET name = ?, muscle_group_id = ?, metric_type = ?, notes = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$name, $groupId, $metric, $notes, $id, $user['id']]);
            Response::json(['ok' => true]);
        }

        $notes = textParam($data, 'notes', 2000, false);
        $stmt = $pdo->prepare('UPDATE exercises SET notes = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$notes, $id, $user['id']]);
        Response::json(['ok' => true]);
    }

    Response::error('Método no permitido', 405);
}

function exerciseSummary(): never
{
    $user = Auth::requireUser();
    $exerciseId = intParam($_GET, 'exercise_id');
    assertExerciseOwner($exerciseId, (int) $user['id']);
    $pdo = Database::pdo();

    $stmt = $pdo->prepare('SELECT id, name, metric_type, notes FROM exercises WHERE id = ? AND user_id = ?');
    $stmt->execute([$exerciseId, $user['id']]);
    $exercise = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT MAX(value) AS best FROM records WHERE exercise_id = ? AND user_id = ?');
    $stmt->execute([$exerciseId, $user['id']]);
    $best = $stmt->fetch()['best'] ?? null;

    $stmt = $pdo->prepare('SELECT value, metric_type, note, recorded_at FROM records WHERE exercise_id = ? AND user_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1');
    $stmt->execute([$exerciseId, $user['id']]);

    Response::json(['ok' => true, 'exercise' => $exercise, 'rm' => $best, 'last_record' => $stmt->fetch() ?: null]);
}

function records(string $method): never
{
    $user = Auth::requireUser();
    $data = Request::input() + $_GET;
    $pdo = Database::pdo();

    if ($method === 'POST' && empty($data['id'])) {
        $exerciseId = intParam($data, 'exercise_id');
        $workoutId = intParam($data, 'workout_id');
        assertExerciseOwner($exerciseId, (int) $user['id']);
        assertWorkoutOwner($workoutId, (int) $user['id']);
        $value = numericValue($data);
        $metric = exerciseMetric($exerciseId, (int) $user['id']);
        $note = textParam($data, 'note', 2000, false);
        $stmt = $pdo->prepare('INSERT INTO records (user_id, exercise_id, workout_id, value, metric_type, note) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $exerciseId, $workoutId, $value, $metric, $note]);
        Response::json(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    $id = intParam($data, 'id');
    assertRecordOwner($id, (int) $user['id']);
    if ($method === 'POST') {
        $value = numericValue($data);
        $note = textParam($data, 'note', 2000, false);
        $stmt = $pdo->prepare('UPDATE records SET value = ?, note = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$value, $note, $id, $user['id']]);
        Response::json(['ok' => true]);
    }
    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM records WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        Response::json(['ok' => true]);
    }

    Response::error('Método no permitido', 405);
}

function history(): never
{
    $user = Auth::requireUser();
    $exerciseId = intParam($_GET, 'exercise_id');
    assertExerciseOwner($exerciseId, (int) $user['id']);
    $pdo = Database::pdo();

    $stmt = $pdo->prepare('SELECT r.id, r.value, r.metric_type, r.note, r.recorded_at, w.name AS workout_name FROM records r JOIN workouts w ON w.id = r.workout_id WHERE r.user_id = ? AND r.exercise_id = ? ORDER BY r.recorded_at DESC, r.id DESC');
    $stmt->execute([$user['id'], $exerciseId]);
    $records = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT DATE(recorded_at) AS day, MAX(value) AS value FROM records WHERE user_id = ? AND exercise_id = ? GROUP BY DATE(recorded_at) ORDER BY day');
    $stmt->execute([$user['id'], $exerciseId]);

    Response::json(['ok' => true, 'records' => $records, 'chart' => $stmt->fetchAll()]);
}

function numericValue(array $data): string
{
    $raw = str_replace(',', '.', trim((string) ($data['value'] ?? '')));
    if (!is_numeric($raw) || (float) $raw < 0) {
        Response::error('Marca inválida');
    }

    return number_format((float) $raw, 2, '.', '');
}

function exerciseMetric(int $exerciseId, int $userId): string
{
    $stmt = Database::pdo()->prepare('SELECT metric_type FROM exercises WHERE id = ? AND user_id = ?');
    $stmt->execute([$exerciseId, $userId]);
    $metric = $stmt->fetchColumn();
    if (!$metric) {
        Response::error('Ejercicio no encontrado', 404);
    }

    return (string) $metric;
}

function assertWorkoutOwner(int $workoutId, int $userId): void
{
    $stmt = Database::pdo()->prepare('SELECT id FROM workouts WHERE id = ? AND user_id = ?');
    $stmt->execute([$workoutId, $userId]);
    if (!$stmt->fetchColumn()) {
        Response::error('Entrenamiento no encontrado', 404);
    }
}

function assertExerciseOwner(int $exerciseId, int $userId): void
{
    $stmt = Database::pdo()->prepare('SELECT id FROM exercises WHERE id = ? AND user_id = ?');
    $stmt->execute([$exerciseId, $userId]);
    if (!$stmt->fetchColumn()) {
        Response::error('Ejercicio no encontrado', 404);
    }
}

function assertRecordOwner(int $recordId, int $userId): void
{
    $stmt = Database::pdo()->prepare('SELECT id FROM records WHERE id = ? AND user_id = ?');
    $stmt->execute([$recordId, $userId]);
    if (!$stmt->fetchColumn()) {
        Response::error('Registro no encontrado', 404);
    }
}
