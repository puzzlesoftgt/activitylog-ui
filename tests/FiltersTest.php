<?php

use Illuminate\Http\Request;
use Puzzlesoft\ActivitylogUi\Filters\ActivityFilter;
use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Tests\Fixtures\User;

function filteredIds(array $query): array
{
    $filter = app(ActivityFilter::class);
    $filters = $filter->fromRequest(Request::create('/activitylog', 'GET', $query));

    return $filter->apply(app(ActivityTable::class)->query(), $filters)
        ->orderBy('id')
        ->pluck('id')
        ->all();
}

it('filters by causer and by system activities', function () {
    $alice = User::create(['username' => 'alice']);
    $bob = User::create(['username' => 'bob']);

    $a = $this->logActivity(['causer_id' => $alice->id]);
    $b = $this->logActivity(['causer_id' => $bob->id]);
    $system = $this->logActivity(['causer_type' => null, 'causer_id' => null]);

    expect(filteredIds(['causer' => $alice->id]))->toBe([$a])
        ->and(filteredIds(['causer' => $bob->id]))->toBe([$b])
        ->and(filteredIds(['causer' => 'system']))->toBe([$system]);
});

it('filters by subject type and subject id', function () {
    $post1 = $this->logActivity(['subject_type' => 'App\\Models\\Post', 'subject_id' => 1]);
    $post2 = $this->logActivity(['subject_type' => 'App\\Models\\Post', 'subject_id' => 2]);
    $order = $this->logActivity(['subject_type' => 'App\\Models\\Order', 'subject_id' => 1]);

    expect(filteredIds(['subject_type' => 'App\\Models\\Post']))->toBe([$post1, $post2])
        ->and(filteredIds(['subject_id' => 1]))->toBe([$post1, $order])
        ->and(filteredIds(['subject_type' => 'App\\Models\\Post', 'subject_id' => 2]))->toBe([$post2]);
});

it('filters by events and ignores unknown events', function () {
    $created = $this->logActivity(['event' => 'created']);
    $updated = $this->logActivity(['event' => 'updated']);
    $deleted = $this->logActivity(['event' => 'deleted']);

    expect(filteredIds(['events' => ['created', 'deleted']]))->toBe([$created, $deleted])
        ->and(filteredIds(['events' => ['hacked']]))->toBe([$created, $updated, $deleted]);
});

it('filters by date presets and custom range', function () {
    $this->travelTo('2026-11-23 10:00:00');

    $today = $this->logActivity(['created_at' => '2026-11-23 08:00:00']);
    $yesterday = $this->logActivity(['created_at' => '2026-11-22 23:00:00']);
    $lastWeek = $this->logActivity(['created_at' => '2026-11-18 12:00:00']);
    $lastMonth = $this->logActivity(['created_at' => '2026-10-30 12:00:00']);
    $old = $this->logActivity(['created_at' => '2025-01-01 12:00:00']);

    expect(filteredIds(['date' => 'today']))->toBe([$today])
        ->and(filteredIds(['date' => 'yesterday']))->toBe([$yesterday])
        ->and(filteredIds(['date' => 'last_7_days']))->toBe([$today, $yesterday, $lastWeek])
        ->and(filteredIds(['date' => 'last_30_days']))->toBe([$today, $yesterday, $lastWeek, $lastMonth])
        ->and(filteredIds(['date' => 'this_month']))->toBe([$today, $yesterday, $lastWeek])
        ->and(filteredIds(['date' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']))->toBe([$lastMonth])
        ->and(filteredIds(['date' => 'all']))->toBe([$today, $yesterday, $lastWeek, $lastMonth, $old]);
});

it('searches the description and the v4 properties json', function () {
    $byDescription = $this->logActivity(['description' => 'Invoice approved']);
    $byProperties = $this->logActivity(['properties' => ['attributes' => ['estado' => 'APROBADO']]]);
    $this->logActivity(['description' => 'other', 'properties' => ['attributes' => ['estado' => 'N']]]);

    expect(filteredIds(['search' => 'approved']))->toBe([$byDescription])
        ->and(filteredIds(['search' => 'aprobado']))->toBe([$byProperties]);
});

it('searches the v5 attribute_changes json', function () {
    $match = $this->logActivity(['attribute_changes' => ['attributes' => ['planilla_id' => 11928]]]);
    $this->logActivity(['attribute_changes' => ['attributes' => ['planilla_id' => 1]]]);

    expect(filteredIds(['search' => '11928']))->toBe([$match]);
});

it('searches json with escaped unicode characters', function () {
    $match = $this->logActivity(['properties' => json_encode(['attributes' => ['nombre' => 'José']])]);

    expect(filteredIds(['search' => 'José']))->toBe([$match]);
});

it('escapes LIKE wildcards in the search term', function () {
    $percent = $this->logActivity(['description' => 'discount 50% applied']);
    $this->logActivity(['description' => 'discount 500 applied']);

    expect(filteredIds(['search' => '50%']))->toBe([$percent]);
});

it('ignores the attribute_changes column on v4 tables', function () {
    $this->createActivityTable(withAttributeChanges: false);

    $match = $this->logActivity(['properties' => ['attributes' => ['estado' => 'C']], 'description' => 'xyz']);

    expect(filteredIds(['search' => 'xyz']))->toBe([$match]);
});

it('filters by log name', function () {
    $default = $this->logActivity(['log_name' => 'default']);
    $billing = $this->logActivity(['log_name' => 'billing']);

    expect(filteredIds(['log_name' => 'billing']))->toBe([$billing])
        ->and(filteredIds([]))->toBe([$default, $billing]);
});

it('filters by batch uuid', function () {
    $batch = '11111111-1111-1111-1111-111111111111';
    $first = $this->logActivity(['batch_uuid' => $batch]);
    $second = $this->logActivity(['batch_uuid' => $batch]);
    $other = $this->logActivity(['batch_uuid' => '22222222-2222-2222-2222-222222222222']);

    expect(filteredIds(['batch' => $batch]))->toBe([$first, $second])
        ->and(filteredIds(['batch' => 'not a uuid']))->toBe([$first, $second, $other]);
});
