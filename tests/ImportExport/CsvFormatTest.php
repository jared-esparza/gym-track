<?php

declare(strict_types=1);

namespace GymTracker\Tests\ImportExport;

use GymTracker\ImportService;
use PHPUnit\Framework\TestCase;

final class CsvFormatTest extends TestCase
{
    public function testExerciseCsvNormalizesValidRows(): void
    {
        $csv = "muscle_group,name,metric_type,notes\nPectoral,Press banca,kg,Barra olimpica\n";

        $result = ImportService::previewExerciseCsv($csv, ImportService::fixtureContext());

        self::assertSame([], $result['errors']);
        self::assertSame('Press banca', $result['plan']['exercises'][0]['name']);
        self::assertSame('kg', $result['plan']['exercises'][0]['metric_type']);
        self::assertSame([], $result['plan']['exercises'][0]['gyms']);
    }

    public function testExerciseCsvAcceptsOptionalGymColumn(): void
    {
        $csv = "muscle_group,name,metric_type,notes,gyms\nPectoral,Press banca,kg,Barra olimpica,Centro|Norte\n";

        $result = ImportService::previewExerciseCsv($csv, ImportService::fixtureContext());

        self::assertSame([], $result['errors']);
        self::assertSame(['Centro', 'Norte'], $result['plan']['exercises'][0]['gyms']);
    }

    public function testExerciseCsvReportsUnknownGym(): void
    {
        $csv = "muscle_group,name,metric_type,notes,gyms\nPectoral,Press banca,kg,,Inventado\n";

        $result = ImportService::previewExerciseCsv($csv, ImportService::fixtureContext());

        self::assertNotEmpty($result['errors']);
        self::assertSame(0, $result['summary']['exercises']);
    }

    public function testExerciseCsvReportsInvalidHeaderGroupAndMetric(): void
    {
        $badHeader = ImportService::previewExerciseCsv("group,name,metric_type,notes\nPectoral,Press,kg,\n", ImportService::fixtureContext());
        $badData = ImportService::previewExerciseCsv("muscle_group,name,metric_type,notes\nInventado,Press,peso,\n", ImportService::fixtureContext());

        self::assertNotEmpty($badHeader['errors']);
        self::assertNotEmpty($badData['errors']);
    }
}
