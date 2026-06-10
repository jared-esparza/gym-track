<?php

declare(strict_types=1);

namespace GymTracker;

final class ImportService
{
    public const MAX_ROWS = 5000;

    public static function previewJson(string $content, array $context): array
    {
        $data = json_decode($content, true);
        $version = (int) ($data['version'] ?? 0);
        if (!is_array($data) || ($data['schema'] ?? '') !== 'gym-tracker-export' || !in_array($version, [1, 2], true)) {
            return self::result(['JSON de backup no reconocido']);
        }

        $plan = self::emptyPlan();
        $errors = [];
        if ($version >= 2) {
            foreach (array_slice($data['gyms'] ?? [], 0, self::MAX_ROWS) as $index => $row) {
                $gym = self::normalizeGym($row, $context, $index + 1, $errors);
                if ($gym) {
                    $plan['gyms'][] = $gym;
                    $context['gyms'][self::key($gym['name'])] = ['id' => $gym['id'], 'name' => $gym['name']];
                }
            }
        }
        foreach (array_slice($data['workouts'] ?? [], 0, self::MAX_ROWS) as $index => $row) {
            $workout = self::normalizeWorkout($row, $context, $index + 1, $errors);
            if ($workout) {
                $plan['workouts'][] = $workout;
                $context['workouts'][self::key($workout['name'])] = ['id' => $workout['id'], 'name' => $workout['name']];
            }
        }
        foreach (array_slice($data['exercises'] ?? [], 0, self::MAX_ROWS) as $index => $row) {
            $exercise = self::normalizeExercise($row, $context, $index + 1, $errors);
            if ($exercise) {
                $plan['exercises'][] = $exercise;
                $context['exercises'][self::exerciseKey($exercise['muscle_group_id'], $exercise['name'])] = [
                    'id' => $exercise['id'],
                    'name' => $exercise['name'],
                    'metric_type' => $exercise['metric_type'],
                    'record_count' => $exercise['record_count'],
                ];
            }
        }
        foreach (array_slice($data['records'] ?? [], 0, self::MAX_ROWS) as $index => $row) {
            $record = self::normalizeRecord($row, $context, $index + 1, $errors);
            if ($record) {
                $plan['records'][] = $record;
            }
        }

        return self::result($errors, [], $plan);
    }

    public static function previewExerciseCsv(string $content, array $context): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (!$lines || trim($lines[0]) === '') {
            return self::result(['CSV vacio']);
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = str_getcsv(array_shift($lines), $delimiter);
        $baseHeader = ['muscle_group', 'name', 'metric_type', 'notes'];
        $gymHeader = ['muscle_group', 'name', 'metric_type', 'notes', 'gyms'];
        if ($header !== $baseHeader && $header !== $gymHeader) {
            return self::result(['Cabecera CSV invalida. Usa: muscle_group,name,metric_type,notes o muscle_group,name,metric_type,notes,gyms']);
        }
        $expected = $header;

        $plan = self::emptyPlan();
        $errors = [];
        foreach (array_slice($lines, 0, self::MAX_ROWS) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, $delimiter);
            $row = array_combine($expected, array_pad($values, count($expected), ''));
            $exercise = self::normalizeExercise($row, $context, $index + 2, $errors);
            if ($exercise) {
                $plan['exercises'][] = $exercise;
                $context['exercises'][self::exerciseKey($exercise['muscle_group_id'], $exercise['name'])] = [
                    'id' => $exercise['id'],
                    'name' => $exercise['name'],
                    'metric_type' => $exercise['metric_type'],
                    'record_count' => $exercise['record_count'],
                ];
            }
        }
        if (count($lines) > self::MAX_ROWS) {
            $errors[] = 'El archivo supera el maximo de filas permitido';
        }

        return self::result($errors, [], $plan);
    }

    public static function fixtureContext(): array
    {
        return [
            'groups' => [
                'pectoral' => ['id' => 1, 'name' => 'Pectoral'],
                'espalda' => ['id' => 7, 'name' => 'Espalda'],
            ],
            'workouts' => [],
            'exercises' => [],
            'gyms' => [
                'centro' => ['id' => 1, 'name' => 'Centro'],
                'norte' => ['id' => 2, 'name' => 'Norte'],
            ],
        ];
    }

    private static function normalizeGym(array $row, array $context, int $rowNumber, array &$errors): ?array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = "Fila {$rowNumber}: gimnasio sin nombre valido";
            return null;
        }

        $existing = $context['gyms'][self::key($name)] ?? null;
        if ($existing && !$existing['id']) {
            return null;
        }

        return ['id' => $existing['id'] ?? null, 'name' => mb_substr($name, 0, 120)];
    }

    private static function normalizeWorkout(array $row, array $context, int $rowNumber, array &$errors): ?array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $groupNames = $row['muscle_groups'] ?? [];
        if ($name === '' || mb_strlen($name) > 120 || !is_array($groupNames)) {
            $errors[] = "Fila {$rowNumber}: entrenamiento invalido";
            return null;
        }
        $groupIds = [];
        foreach ($groupNames as $groupName) {
            $group = $context['groups'][self::key((string) $groupName)] ?? null;
            if (!$group) {
                $errors[] = "Fila {$rowNumber}: grupo muscular desconocido";
                continue;
            }
            $groupIds[] = $group['id'];
        }
        if (!$groupIds) {
            return null;
        }
        $existing = $context['workouts'][self::key($name)] ?? null;
        if ($existing && !$existing['id']) {
            return null;
        }

        return ['id' => $existing['id'] ?? null, 'name' => mb_substr($name, 0, 120), 'muscle_group_ids' => array_values(array_unique($groupIds))];
    }

    private static function normalizeExercise(array $row, array $context, int $rowNumber, array &$errors): ?array
    {
        $groupName = trim((string) ($row['muscle_group'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $metric = trim((string) ($row['metric_type'] ?? ''));
        $group = $context['groups'][self::key($groupName)] ?? null;
        $gyms = self::normalizeGymList($row['gyms'] ?? [], $context, $rowNumber, $errors);
        if ($gyms === null) {
            return null;
        }

        if (!$group) {
            $errors[] = "Fila {$rowNumber}: grupo muscular desconocido";
            return null;
        }
        if ($name === '' || mb_strlen($name) > 140) {
            $errors[] = "Fila {$rowNumber}: ejercicio sin nombre valido";
            return null;
        }
        if (!in_array($metric, ['kg', 'reps', 'min', 'km'], true)) {
            $errors[] = "Fila {$rowNumber}: tipo de marca invalido";
            return null;
        }

        $existing = $context['exercises'][self::exerciseKey($group['id'], $name)] ?? null;
        if ($existing && !$existing['id']) {
            return null;
        }
        if ($existing && $existing['record_count'] > 0 && $existing['metric_type'] !== $metric) {
            $errors[] = "Fila {$rowNumber}: no se puede cambiar el tipo de marca";
            return null;
        }

        return [
            'id' => $existing['id'] ?? null,
            'record_count' => $existing['record_count'] ?? 0,
            'muscle_group_id' => $group['id'],
            'muscle_group' => $group['name'],
            'name' => mb_substr($name, 0, 140),
            'metric_type' => $metric,
            'notes' => mb_substr(trim((string) ($row['notes'] ?? '')), 0, 2000),
            'gym_ids' => array_column($gyms, 'id'),
            'gyms' => array_column($gyms, 'name'),
        ];
    }

    private static function normalizeRecord(array $row, array $context, int $rowNumber, array &$errors): ?array
    {
        $group = $context['groups'][self::key((string) ($row['muscle_group'] ?? ''))] ?? null;
        $workout = $context['workouts'][self::key((string) ($row['workout'] ?? ''))] ?? null;
        $exerciseName = trim((string) ($row['exercise'] ?? ''));
        if (!$group || !$workout || $exerciseName === '') {
            $errors[] = "Fila {$rowNumber}: registro no resoluble";
            return null;
        }
        $exercise = $context['exercises'][self::exerciseKey($group['id'], $exerciseName)] ?? null;
        $gym = self::optionalGym($row['gym'] ?? null, $context, $rowNumber, $errors);
        if ($gym === false) {
            return null;
        }
        $value = str_replace(',', '.', trim((string) ($row['value'] ?? '')));
        $recordedAt = trim((string) ($row['recorded_at'] ?? ''));
        if (!$exercise || !is_numeric($value) || (float) $value < 0 || ($row['metric_type'] ?? '') !== $exercise['metric_type'] || strtotime($recordedAt) === false) {
            $errors[] = "Fila {$rowNumber}: registro invalido";
            return null;
        }

        return [
            'exercise_id' => $exercise['id'],
            'exercise_name' => $exerciseName,
            'muscle_group_id' => $group['id'],
            'workout_id' => $workout['id'],
            'workout_name' => $workout['name'],
            'gym_id' => $gym['id'] ?? null,
            'gym_name' => $gym['name'] ?? null,
            'value' => number_format((float) $value, 2, '.', ''),
            'metric_type' => $exercise['metric_type'],
            'note' => mb_substr(trim((string) ($row['note'] ?? '')), 0, 2000),
            'recorded_at' => date('Y-m-d H:i:s', strtotime($recordedAt)),
        ];
    }

    private static function normalizeGymList(mixed $value, array $context, int $rowNumber, array &$errors): ?array
    {
        if (!is_array($value)) {
            $value = array_filter(array_map('trim', explode('|', (string) $value)));
        }
        $gyms = [];
        foreach ($value as $gymName) {
            $name = trim((string) $gymName);
            if ($name === '') {
                continue;
            }
            $gym = $context['gyms'][self::key($name)] ?? null;
            if (!$gym) {
                $errors[] = "Fila {$rowNumber}: gimnasio desconocido";
                return null;
            }
            $key = $gym['id'] ? 'id:' . $gym['id'] : 'name:' . self::key($gym['name']);
            $gyms[$key] = ['id' => $gym['id'] ? (int) $gym['id'] : null, 'name' => $gym['name']];
        }

        return array_values($gyms);
    }

    private static function optionalGym(mixed $value, array $context, int $rowNumber, array &$errors): array|false|null
    {
        $name = trim((string) ($value ?? ''));
        if ($name === '') {
            return null;
        }

        $gym = $context['gyms'][self::key($name)] ?? null;
        if (!$gym) {
            $errors[] = "Fila {$rowNumber}: gimnasio desconocido";
            return false;
        }

        return ['id' => $gym['id'] ? (int) $gym['id'] : null, 'name' => $gym['name']];
    }

    private static function result(array $errors = [], array $warnings = [], ?array $plan = null): array
    {
        $plan ??= self::emptyPlan();
        return [
            'plan' => $plan,
            'summary' => [
                'gyms' => count($plan['gyms'] ?? []),
                'workouts' => count($plan['workouts']),
                'exercises' => count($plan['exercises']),
                'records' => count($plan['records']),
                'errors' => count($errors),
                'warnings' => count($warnings),
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private static function emptyPlan(): array
    {
        return ['gyms' => [], 'workouts' => [], 'exercises' => [], 'records' => []];
    }

    private static function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private static function exerciseKey(int $groupId, string $name): string
    {
        return $groupId . '|' . self::key($name);
    }
}
