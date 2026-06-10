<?php

declare(strict_types=1);

use GymTracker\Auth;
use GymTracker\Config;
use GymTracker\Database;
use GymTracker\Mailer;
use GymTracker\Request;
use GymTracker\RegistrationException;
use GymTracker\RegistrationService;
use GymTracker\Response;
use GymTracker\ImportService;
use GymTracker\RateLimiter;
use GymTracker\Security;

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if (Security::isMutatingMethod($method) && !Security::validCsrfToken($_SESSION, Security::csrfHeader())) {
        Response::error('Token CSRF invÃ¡lido', 403);
    }

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
        'export' => exportData(),
        'import-preview' => postOnly($method) && importPreview(),
        'import-confirm' => postOnly($method) && importConfirm(),
        'import-cancel' => postOnly($method) && importCancel(),
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

function throttle(string $action, ?string $identifier = null, int $maxAttempts = 10, int $windowSeconds = 900): void
{
    $identity = $identifier ?: clientIp();
    if (!RateLimiter::attempt(Database::pdo(), $action, $identity, $maxAttempts, $windowSeconds)) {
        Response::error('Demasiados intentos. Espera unos minutos antes de volver a probar.', 429);
    }
}

function clientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 80);
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
        Response::json(['ok' => true, 'user' => null, 'csrf_token' => Security::csrfToken($_SESSION)]);
    }

    Response::json(['ok' => true, 'user' => $user, 'csrf_token' => Security::csrfToken($_SESSION)]);
}

function register(): never
{
    $data = Request::input();
    $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($data['password'] ?? '');
    throttle('register', clientIp(), 5, 3600);
    if (!$email || strlen($password) < 8) {
        Response::error('Email válido y contraseña de 8 caracteres mínimo requeridos');
    }

    $pdo = Database::pdo();
    try {
        $result = RegistrationService::register($pdo, $email, $password, 'sendVerification');
    } catch (RegistrationException $e) {
        Response::error($e->getMessage(), $e->status());
    }

    Response::json(['ok' => true, 'message' => $result['message']]);
}

function login(): never
{
    $data = Request::input();
    $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($data['password'] ?? '');
    throttle('login', clientIp() . '|' . (string) $email, 8, 900);
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
    throttle('verify-email', clientIp(), 20, 3600);
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
    throttle('forgot-password', clientIp() . '|' . (string) $email, 5, 3600);
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
    throttle('reset-password', clientIp(), 10, 3600);
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

function exportData(): never
{
    $user = Auth::requireUser();
    $format = (string) ($_GET['format'] ?? 'json');
    $userId = (int) $user['id'];

    if ($format === 'json') {
        downloadJson('gym-tracker-backup.json', [
            'schema' => 'gym-tracker-export',
            'version' => 1,
            'exported_at' => gmdate('c'),
            'workouts' => exportWorkouts($userId),
            'exercises' => exportExercises($userId),
            'records' => exportRecords($userId),
        ]);
    }

    if ($format !== 'csv') {
        Response::error('Formato de exportaciÃ³n no soportado');
    }

    $type = (string) ($_GET['type'] ?? '');
    if ($type === 'exercises') {
        downloadCsv('exercises.csv', ['muscle_group', 'name', 'metric_type', 'notes'], exportExerciseCsvRows($userId));
    }
    if ($type === 'workouts') {
        downloadCsv('workouts.csv', ['name', 'muscle_groups'], exportWorkoutCsvRows($userId));
    }
    if ($type === 'records') {
        downloadCsv('records.csv', ['muscle_group', 'exercise', 'workout', 'value', 'metric_type', 'note', 'recorded_at'], exportRecordCsvRows($userId));
    }

    Response::error('Tipo de CSV no soportado');
}

function exportWorkouts(int $userId): array
{
    $stmt = Database::pdo()->prepare("
        SELECT w.id, w.name, mg.name AS muscle_group
        FROM workouts w
        LEFT JOIN workout_muscle_groups wmg ON wmg.workout_id = w.id
        LEFT JOIN muscle_groups mg ON mg.id = wmg.muscle_group_id
        WHERE w.user_id = ?
        ORDER BY w.name, mg.sort_order
    ");
    $stmt->execute([$userId]);
    $workouts = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $workouts[$name] ??= ['name' => $name, 'muscle_groups' => []];
        if ($row['muscle_group']) {
            $workouts[$name]['muscle_groups'][] = $row['muscle_group'];
        }
    }

    return array_values($workouts);
}

function exportExercises(int $userId): array
{
    $stmt = Database::pdo()->prepare("
        SELECT mg.name AS muscle_group, e.name, e.metric_type, e.notes
        FROM exercises e
        JOIN muscle_groups mg ON mg.id = e.muscle_group_id
        WHERE e.user_id = ?
        ORDER BY mg.sort_order, e.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function exportRecords(int $userId): array
{
    $stmt = Database::pdo()->prepare("
        SELECT mg.name AS muscle_group, e.name AS exercise, w.name AS workout, r.value, r.metric_type, r.note, r.recorded_at
        FROM records r
        JOIN exercises e ON e.id = r.exercise_id
        JOIN muscle_groups mg ON mg.id = e.muscle_group_id
        JOIN workouts w ON w.id = r.workout_id
        WHERE r.user_id = ?
        ORDER BY r.recorded_at, r.id
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function exportExerciseCsvRows(int $userId): array
{
    return array_map(static fn (array $row): array => [
        $row['muscle_group'],
        $row['name'],
        $row['metric_type'],
        $row['notes'] ?? '',
    ], exportExercises($userId));
}

function exportWorkoutCsvRows(int $userId): array
{
    return array_map(static fn (array $row): array => [
        $row['name'],
        implode('|', $row['muscle_groups']),
    ], exportWorkouts($userId));
}

function exportRecordCsvRows(int $userId): array
{
    return array_map(static fn (array $row): array => [
        $row['muscle_group'],
        $row['exercise'],
        $row['workout'],
        $row['value'],
        $row['metric_type'],
        $row['note'] ?? '',
        $row['recorded_at'],
    ], exportRecords($userId));
}

function downloadJson(string $filename, array $payload): never
{
    Security::securityHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function downloadCsv(string $filename, array $headers, array $rows): never
{
    Security::securityHeaders();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function importPreview(): never
{
    $user = Auth::requireUser();
    throttle('import-preview', clientIp() . '|user:' . $user['id'], 20, 3600);
    $payload = uploadedImportPayload();
    $result = previewImportPayload((int) $user['id'], $payload['content'], $payload['filename']);
    $token = null;

    if (!$result['errors']) {
        $token = bin2hex(random_bytes(16));
        $_SESSION['import_preview'] = [
            'import_token' => $token,
            'plan' => $result['plan'],
            'summary' => $result['summary'],
        ];
    } else {
        unset($_SESSION['import_preview']);
    }

    Response::json([
        'ok' => true,
        'import_token' => $token,
        'summary' => $result['summary'],
        'errors' => $result['errors'],
        'warnings' => $result['warnings'],
    ]);
}

function importConfirm(): never
{
    $user = Auth::requireUser();
    $data = Request::input();
    $token = (string) ($data['import_token'] ?? '');
    $preview = $_SESSION['import_preview'] ?? null;
    if (!$preview || !hash_equals((string) $preview['import_token'], $token)) {
        Response::error('PrevisualizaciÃ³n de importaciÃ³n caducada', 400);
    }

    $summary = applyImportPlan((int) $user['id'], $preview['plan']);
    unset($_SESSION['import_preview']);
    Response::json(['ok' => true, 'summary' => $summary]);
}

function importCancel(): never
{
    Auth::requireUser();
    unset($_SESSION['import_preview']);
    Response::json(['ok' => true]);
}

function uploadedImportPayload(): array
{
    $maxBytes = 2 * 1024 * 1024;
    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $filename = (string) $_FILES['file']['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = (string) ($_FILES['file']['type'] ?? '');
        $allowedMimes = ['application/json', 'text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream'];
        if (!in_array($extension, ['json', 'csv'], true) || ($mime !== '' && !in_array($mime, $allowedMimes, true))) {
            Response::error('Tipo de archivo de importaciÃ³n no permitido');
        }
        if ((int) $_FILES['file']['size'] > $maxBytes) {
            Response::error('El archivo supera el tamaÃ±o mÃ¡ximo de 2 MB');
        }
        return [
            'filename' => $filename,
            'content' => file_get_contents($_FILES['file']['tmp_name']) ?: '',
        ];
    }

    $data = Request::input();
    $content = (string) ($data['content'] ?? '');
    if ($content === '' || strlen($content) > $maxBytes) {
        Response::error('Archivo de importaciÃ³n invÃ¡lido');
    }

    $filename = (string) ($data['filename'] ?? 'import.json');
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['json', 'csv'], true)) {
        Response::error('Tipo de archivo de importaciÃ³n no permitido');
    }

    return ['filename' => $filename, 'content' => $content];
}

function previewImportPayload(int $userId, string $content, string $filename): array
{
    $trimmed = trim($content);
    if ($trimmed === '') {
        return importResult([], ['El archivo estÃ¡ vacÃ­o'], []);
    }

    $context = importContext($userId);
    if (str_ends_with(strtolower($filename), '.json') || str_starts_with($trimmed, '{')) {
        return ImportService::previewJson($trimmed, $context);
    }

    return ImportService::previewExerciseCsv($content, $context);
}

function previewJsonImport(int $userId, string $content): array
{
    $data = json_decode($content, true);
    if (!is_array($data) || ($data['schema'] ?? '') !== 'gym-tracker-export' || (int) ($data['version'] ?? 0) !== 1) {
        return importResult([], ['JSON de backup no reconocido'], []);
    }

    $plan = ['workouts' => [], 'exercises' => [], 'records' => []];
    $errors = [];
    $warnings = [];
    $context = importContext($userId);
    foreach (($data['workouts'] ?? []) as $index => $row) {
        $workout = normalizeWorkoutImport($row, $context, $index + 1, $errors);
        if ($workout) {
            $plan['workouts'][] = $workout;
            $context['workouts'][normalizeKey($workout['name'])] = ['id' => $workout['id'], 'name' => $workout['name']];
        }
    }
    foreach (($data['exercises'] ?? []) as $index => $row) {
        $exercise = normalizeExerciseImport($row, $context, $index + 1, $errors);
        if ($exercise) {
            $plan['exercises'][] = $exercise;
            $key = exerciseImportKey($exercise['muscle_group_id'], $exercise['name']);
            $context['exercises'][$key] = [
                'id' => $exercise['id'],
                'name' => $exercise['name'],
                'metric_type' => $exercise['metric_type'],
                'record_count' => $exercise['record_count'],
            ];
        }
    }
    foreach (($data['records'] ?? []) as $index => $row) {
        $record = normalizeRecordImport($row, $context, $index + 1, $errors);
        if ($record) {
            $plan['records'][] = $record;
        }
    }

    return importResult($plan, $errors, $warnings);
}

function previewExerciseCsvImport(int $userId, string $content): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (!$lines || trim($lines[0]) === '') {
        return importResult([], ['CSV vacÃ­o'], []);
    }

    $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
    $header = str_getcsv(array_shift($lines), $delimiter);
    $expected = ['muscle_group', 'name', 'metric_type', 'notes'];
    if ($header !== $expected) {
        return importResult([], ['Cabecera CSV invÃ¡lida. Usa: muscle_group,name,metric_type,notes'], []);
    }

    $context = importContext($userId);
    $plan = ['workouts' => [], 'exercises' => [], 'records' => []];
    $errors = [];
    foreach ($lines as $index => $line) {
        if (trim($line) === '') {
            continue;
        }
        $values = str_getcsv($line, $delimiter);
        $row = array_combine($expected, array_pad($values, count($expected), ''));
        $exercise = normalizeExerciseImport($row, $context, $index + 2, $errors);
        if ($exercise) {
            $plan['exercises'][] = $exercise;
            $context['exercises'][exerciseImportKey($exercise['muscle_group_id'], $exercise['name'])] = [
                'id' => $exercise['id'],
                'name' => $exercise['name'],
                'metric_type' => $exercise['metric_type'],
                'record_count' => $exercise['record_count'],
            ];
        }
    }

    return importResult($plan, $errors, []);
}

function importContext(int $userId): array
{
    $pdo = Database::pdo();
    $groups = [];
    foreach ($pdo->query('SELECT id, name FROM muscle_groups')->fetchAll() as $row) {
        $groups[normalizeKey($row['name'])] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }

    $stmt = $pdo->prepare('SELECT id, name FROM workouts WHERE user_id = ?');
    $stmt->execute([$userId]);
    $workouts = [];
    foreach ($stmt->fetchAll() as $row) {
        $workouts[normalizeKey($row['name'])] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }

    $stmt = $pdo->prepare('SELECT e.id, e.muscle_group_id, e.name, e.metric_type, COUNT(r.id) AS record_count FROM exercises e LEFT JOIN records r ON r.exercise_id = e.id AND r.user_id = e.user_id WHERE e.user_id = ? GROUP BY e.id, e.muscle_group_id, e.name, e.metric_type');
    $stmt->execute([$userId]);
    $exercises = [];
    foreach ($stmt->fetchAll() as $row) {
        $exercises[exerciseImportKey((int) $row['muscle_group_id'], $row['name'])] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'metric_type' => $row['metric_type'],
            'record_count' => (int) $row['record_count'],
        ];
    }

    return ['groups' => $groups, 'workouts' => $workouts, 'exercises' => $exercises];
}

function normalizeWorkoutImport(array $row, array $context, int $rowNumber, array &$errors): ?array
{
    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = "Fila {$rowNumber}: entrenamiento sin nombre vÃ¡lido";
        return null;
    }

    $groupNames = $row['muscle_groups'] ?? [];
    if (!is_array($groupNames)) {
        $groupNames = array_filter(array_map('trim', explode('|', (string) $groupNames)));
    }
    $groupIds = [];
    foreach ($groupNames as $groupName) {
        $group = $context['groups'][normalizeKey((string) $groupName)] ?? null;
        if (!$group) {
            $errors[] = "Fila {$rowNumber}: grupo muscular desconocido {$groupName}";
            continue;
        }
        $groupIds[] = $group['id'];
    }
    if (!$groupIds) {
        $errors[] = "Fila {$rowNumber}: entrenamiento sin grupos musculares";
        return null;
    }

    $existing = $context['workouts'][normalizeKey($name)] ?? null;
    if ($existing && !$existing['id']) {
        return null;
    }
    return ['id' => $existing['id'] ?? null, 'name' => mb_substr($name, 0, 120), 'muscle_group_ids' => array_values(array_unique($groupIds))];
}

function normalizeExerciseImport(array $row, array $context, int $rowNumber, array &$errors): ?array
{
    $groupName = trim((string) ($row['muscle_group'] ?? ''));
    $name = trim((string) ($row['name'] ?? ''));
    $metric = trim((string) ($row['metric_type'] ?? ''));
    $notes = trim((string) ($row['notes'] ?? ''));
    $group = $context['groups'][normalizeKey($groupName)] ?? null;

    if (!$group) {
        $errors[] = "Fila {$rowNumber}: grupo muscular desconocido {$groupName}";
        return null;
    }
    if ($name === '' || mb_strlen($name) > 140) {
        $errors[] = "Fila {$rowNumber}: ejercicio sin nombre vÃ¡lido";
        return null;
    }
    if (!in_array($metric, ['kg', 'reps', 'min', 'km'], true)) {
        $errors[] = "Fila {$rowNumber}: tipo de marca invÃ¡lido";
        return null;
    }

    $existing = $context['exercises'][exerciseImportKey($group['id'], $name)] ?? null;
    if ($existing && !$existing['id']) {
        return null;
    }
    if ($existing && $existing['record_count'] > 0 && $existing['metric_type'] !== $metric) {
        $errors[] = "Fila {$rowNumber}: no se puede cambiar el tipo de marca de {$name} porque tiene registros";
        return null;
    }

    return [
        'id' => $existing['id'] ?? null,
        'record_count' => $existing['record_count'] ?? 0,
        'muscle_group_id' => $group['id'],
        'muscle_group' => $group['name'],
        'name' => mb_substr($name, 0, 140),
        'metric_type' => $metric,
        'notes' => mb_substr($notes, 0, 2000),
    ];
}

function normalizeRecordImport(array $row, array $context, int $rowNumber, array &$errors): ?array
{
    $group = $context['groups'][normalizeKey((string) ($row['muscle_group'] ?? ''))] ?? null;
    $workout = $context['workouts'][normalizeKey((string) ($row['workout'] ?? ''))] ?? null;
    $exerciseName = trim((string) ($row['exercise'] ?? ''));
    if (!$group || !$workout || $exerciseName === '') {
        $errors[] = "Fila {$rowNumber}: registro con ejercicio, grupo o entrenamiento no resoluble";
        return null;
    }

    $exercise = $context['exercises'][exerciseImportKey($group['id'], $exerciseName)] ?? null;
    if (!$exercise) {
        $errors[] = "Fila {$rowNumber}: ejercicio no encontrado para el registro";
        return null;
    }

    $value = str_replace(',', '.', trim((string) ($row['value'] ?? '')));
    $metric = trim((string) ($row['metric_type'] ?? ''));
    $recordedAt = trim((string) ($row['recorded_at'] ?? ''));
    if (!is_numeric($value) || (float) $value < 0 || $metric !== $exercise['metric_type'] || strtotime($recordedAt) === false) {
        $errors[] = "Fila {$rowNumber}: registro con marca, tipo o fecha invÃ¡lida";
        return null;
    }

    return [
        'exercise_id' => $exercise['id'],
        'exercise_name' => $exerciseName,
        'muscle_group_id' => $group['id'],
        'workout_id' => $workout['id'],
        'workout_name' => $workout['name'],
        'value' => number_format((float) $value, 2, '.', ''),
        'metric_type' => $metric,
        'note' => mb_substr(trim((string) ($row['note'] ?? '')), 0, 2000),
        'recorded_at' => date('Y-m-d H:i:s', strtotime($recordedAt)),
    ];
}

function importResult(array $plan, array $errors, array $warnings): array
{
    $summary = [
        'workouts' => count($plan['workouts'] ?? []),
        'exercises' => count($plan['exercises'] ?? []),
        'records' => count($plan['records'] ?? []),
        'errors' => count($errors),
        'warnings' => count($warnings),
    ];

    return ['plan' => $plan, 'summary' => $summary, 'errors' => $errors, 'warnings' => $warnings];
}

function applyImportPlan(int $userId, array $plan): array
{
    $pdo = Database::pdo();
    $summary = ['workouts' => 0, 'exercises' => 0, 'records' => 0, 'skipped_records' => 0];
    $pdo->beginTransaction();
    try {
        foreach ($plan['workouts'] ?? [] as $workout) {
            $workoutId = ensureWorkout($userId, $workout['name'], $workout['muscle_group_ids']);
            if (!$workout['id']) {
                $summary['workouts']++;
            }
            $workout['id'] = $workoutId;
        }
        foreach ($plan['exercises'] ?? [] as $exercise) {
            ensureExercise($userId, $exercise);
            $summary['exercises']++;
        }
        foreach ($plan['records'] ?? [] as $record) {
            if (insertRecordIfMissing($userId, $record)) {
                $summary['records']++;
            } else {
                $summary['skipped_records']++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $summary;
}

function ensureWorkout(int $userId, string $name, array $groupIds): int
{
    $pdo = Database::pdo();
    $stmt = $pdo->prepare('SELECT id FROM workouts WHERE user_id = ? AND name = ?');
    $stmt->execute([$userId, $name]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if (!$id) {
        $stmt = $pdo->prepare('INSERT INTO workouts (user_id, name) VALUES (?, ?)');
        $stmt->execute([$userId, $name]);
        $id = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO workout_muscle_groups (workout_id, muscle_group_id) VALUES (?, ?)');
    foreach ($groupIds as $groupId) {
        $stmt->execute([$id, $groupId]);
    }

    return $id;
}

function ensureExercise(int $userId, array $exercise): int
{
    $pdo = Database::pdo();
    if ($exercise['id']) {
        $stmt = $pdo->prepare('UPDATE exercises SET name = ?, muscle_group_id = ?, metric_type = ?, notes = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$exercise['name'], $exercise['muscle_group_id'], $exercise['metric_type'], $exercise['notes'], $exercise['id'], $userId]);
        return (int) $exercise['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO exercises (user_id, muscle_group_id, name, metric_type, notes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $exercise['muscle_group_id'], $exercise['name'], $exercise['metric_type'], $exercise['notes']]);
    return (int) $pdo->lastInsertId();
}

function insertRecordIfMissing(int $userId, array $record): bool
{
    if (!$record['exercise_id']) {
        $stmt = Database::pdo()->prepare('SELECT id FROM exercises WHERE user_id = ? AND muscle_group_id = ? AND name = ?');
        $stmt->execute([$userId, $record['muscle_group_id'], $record['exercise_name']]);
        $record['exercise_id'] = (int) $stmt->fetchColumn();
    }
    if (!$record['workout_id']) {
        $stmt = Database::pdo()->prepare('SELECT id FROM workouts WHERE user_id = ? AND name = ?');
        $stmt->execute([$userId, $record['workout_name']]);
        $record['workout_id'] = (int) $stmt->fetchColumn();
    }
    if (!$record['exercise_id'] || !$record['workout_id']) {
        Response::error('No se pudo resolver un registro importado', 400);
    }

    $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM records WHERE user_id = ? AND exercise_id = ? AND workout_id = ? AND recorded_at = ? AND value = ? AND COALESCE(note, "") = ?');
    $stmt->execute([$userId, $record['exercise_id'], $record['workout_id'], $record['recorded_at'], $record['value'], $record['note']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return false;
    }

    $stmt = Database::pdo()->prepare('INSERT INTO records (user_id, exercise_id, workout_id, value, metric_type, note, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $record['exercise_id'], $record['workout_id'], $record['value'], $record['metric_type'], $record['note'], $record['recorded_at']]);
    return true;
}

function normalizeKey(string $value): string
{
    return mb_strtolower(trim($value));
}

function exerciseImportKey(int $groupId, string $name): string
{
    return $groupId . '|' . normalizeKey($name);
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
