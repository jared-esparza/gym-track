<?php

declare(strict_types=1);

namespace GymTracker\Tests\ImportExport;

use GymTracker\ImportService;
use PHPUnit\Framework\TestCase;

final class ImportPreviewTest extends TestCase
{
    public function testJsonBackupProducesImportPlan(): void
    {
        $json = json_encode([
            'schema' => 'gym-tracker-export',
            'version' => 1,
            'workouts' => [['name' => 'Push', 'muscle_groups' => ['Pectoral']]],
            'exercises' => [['muscle_group' => 'Pectoral', 'name' => 'Press banca', 'metric_type' => 'kg', 'notes' => '']],
            'records' => [['muscle_group' => 'Pectoral', 'exercise' => 'Press banca', 'workout' => 'Push', 'value' => '80', 'metric_type' => 'kg', 'note' => '', 'recorded_at' => '2026-06-09 10:00:00']],
        ], JSON_THROW_ON_ERROR);

        $result = ImportService::previewJson($json, ImportService::fixtureContext());

        self::assertSame([], $result['errors']);
        self::assertCount(1, $result['plan']['workouts']);
        self::assertCount(1, $result['plan']['exercises']);
        self::assertCount(1, $result['plan']['records']);
        self::assertArrayHasKey('gym_id', $result['plan']['records'][0]);
        self::assertNull($result['plan']['records'][0]['gym_id']);
    }

    public function testJsonBackupVersion2ProducesGymAwarePlan(): void
    {
        $json = json_encode([
            'schema' => 'gym-tracker-export',
            'version' => 2,
            'gyms' => [['name' => 'Centro']],
            'workouts' => [['name' => 'Push', 'muscle_groups' => ['Pectoral']]],
            'exercises' => [['muscle_group' => 'Pectoral', 'name' => 'Press banca', 'metric_type' => 'kg', 'notes' => '', 'gyms' => ['Centro']]],
            'records' => [['muscle_group' => 'Pectoral', 'exercise' => 'Press banca', 'workout' => 'Push', 'gym' => 'Centro', 'value' => '80', 'metric_type' => 'kg', 'note' => '', 'recorded_at' => '2026-06-09 10:00:00']],
        ], JSON_THROW_ON_ERROR);

        $result = ImportService::previewJson($json, ImportService::fixtureContext());

        self::assertSame([], $result['errors']);
        self::assertSame('Centro', $result['plan']['gyms'][0]['name']);
        self::assertSame(['Centro'], $result['plan']['exercises'][0]['gyms']);
        self::assertSame('Centro', $result['plan']['records'][0]['gym_name']);
    }

    public function testJsonBackupVersion2CanDefineNewGymsBeforeUsingThem(): void
    {
        $json = json_encode([
            'schema' => 'gym-tracker-export',
            'version' => 2,
            'gyms' => [['name' => 'Centro'], ['name' => 'Norte']],
            'workouts' => [['name' => 'Push', 'muscle_groups' => ['Pectoral']]],
            'exercises' => [['muscle_group' => 'Pectoral', 'name' => 'Press banca', 'metric_type' => 'kg', 'notes' => '', 'gyms' => ['Centro', 'Norte']]],
            'records' => [['muscle_group' => 'Pectoral', 'exercise' => 'Press banca', 'workout' => 'Push', 'gym' => 'Centro', 'value' => '80', 'metric_type' => 'kg', 'note' => '', 'recorded_at' => '2026-06-09 10:00:00']],
        ], JSON_THROW_ON_ERROR);
        $context = ImportService::fixtureContext();
        $context['gyms'] = [];

        $result = ImportService::previewJson($json, $context);

        self::assertSame([], $result['errors']);
        self::assertSame(['Centro', 'Norte'], $result['plan']['exercises'][0]['gyms']);
        self::assertNull($result['plan']['records'][0]['gym_id']);
        self::assertSame('Centro', $result['plan']['records'][0]['gym_name']);
    }
}
