<?php
declare(strict_types=1);
namespace GymTracker\Tests\ImportExport;

use GymTracker\ImportService;
use PHPUnit\Framework\TestCase;

final class FlexibleImportTest extends TestCase
{
    public function testVersionThreeRestoresUnclassifiedExerciseAndFreeMark(): void
    {
        $result = ImportService::previewJson(json_encode([
            'schema' => 'gym-tracker-export', 'version' => 3,
            'workouts' => [['name' => 'Libre', 'exercises' => [['muscle_group' => null, 'name' => 'Máquina nueva']]]],
            'exercises' => [['muscle_group' => null, 'name' => 'Máquina nueva', 'metric_type' => 'kg']],
            'records' => [['muscle_group' => null, 'exercise' => 'Máquina nueva', 'workout' => null,
                'value' => '20', 'metric_type' => 'kg', 'recorded_at' => '2026-10-19 12:00:00']],
        ]), ImportService::fixtureContext());
        self::assertSame([], $result['errors']);
        self::assertNull($result['plan']['exercises'][0]['muscle_group_id']);
        self::assertNull($result['plan']['records'][0]['workout_id']);
        self::assertCount(1, $result['plan']['workouts'][0]['exercises']);
    }

    public function testCsvAllowsEmptyGroupButRejectsUnknownGroup(): void
    {
        $result = ImportService::previewExerciseCsv("muscle_group,name,metric_type,notes\n,Libre,kg,", ImportService::fixtureContext());
        self::assertSame([], $result['errors']);
        self::assertNull($result['plan']['exercises'][0]['muscle_group_id']);
        $invalid = ImportService::previewExerciseCsv("muscle_group,name,metric_type,notes\nInventado,Libre,kg,", ImportService::fixtureContext());
        self::assertNotEmpty($invalid['errors']);
    }

    public function testMissingWorkoutExerciseReferenceIsRejected(): void
    {
        $result = ImportService::previewJson(json_encode([
            'schema' => 'gym-tracker-export', 'version' => 3,
            'workouts' => [['name' => 'Libre', 'exercises' => [['name' => 'No existe', 'muscle_group' => null]]]],
        ]), ImportService::fixtureContext());
        self::assertNotEmpty($result['errors']);
    }
}
