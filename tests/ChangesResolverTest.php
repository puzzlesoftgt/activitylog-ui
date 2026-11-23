<?php

use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Support\ChangesResolver;

function findActivity(int $id)
{
    return app(ActivityTable::class)->query()->findOrFail($id);
}

it('builds a diff from v4 properties', function () {
    $this->createActivityTable(withAttributeChanges: false);

    $id = $this->logActivity([
        'event' => 'updated',
        'properties' => [
            'old' => ['estado' => 'N', 'pago_pendiente' => 800],
            'attributes' => ['estado' => 'C', 'pago_pendiente' => 0],
            'ip' => '127.0.0.1',
        ],
    ]);

    $resolver = app(ChangesResolver::class);
    $changes = $resolver->rows(findActivity($id));

    expect($changes['mode'])->toBe('diff')
        ->and($changes['rows'])->toHaveCount(2)
        ->and($changes['rows'][0])->toMatchArray(['field' => 'estado', 'old' => 'N', 'new' => 'C', 'changed' => true])
        ->and($resolver->resolve(findActivity($id))['extra'])->toBe(['ip' => '127.0.0.1']);
});

it('builds a diff from v5 attribute_changes', function () {
    $id = $this->logActivity([
        'event' => 'updated',
        'attribute_changes' => [
            'old' => ['fecha_pago' => null],
            'attributes' => ['fecha_pago' => '2026-11-21T06:00:00.000000Z'],
        ],
        'properties' => ['source' => 'api'],
    ]);

    $resolver = app(ChangesResolver::class);
    $changes = $resolver->rows(findActivity($id));

    expect($changes['mode'])->toBe('diff')
        ->and($changes['rows'][0])->toMatchArray(['field' => 'fecha_pago', 'old' => null, 'new' => '2026-11-21T06:00:00.000000Z'])
        ->and($resolver->resolve(findActivity($id))['extra'])->toBe(['source' => 'api']);
});

it('shows created values', function () {
    $id = $this->logActivity([
        'event' => 'created',
        'attribute_changes' => ['attributes' => ['name' => 'Foo', 'active' => true]],
    ]);

    $changes = app(ChangesResolver::class)->rows(findActivity($id));

    expect($changes['mode'])->toBe('values')
        ->and(array_column($changes['rows'], 'new', 'field'))->toBe(['name' => 'Foo', 'active' => true]);
});

it('shows deleted values from old or attributes', function () {
    $fromOld = $this->logActivity(['event' => 'deleted', 'properties' => ['old' => ['name' => 'Foo']]]);
    $fromAttributes = $this->logActivity(['event' => 'deleted', 'properties' => ['attributes' => ['name' => 'Bar']]]);

    $resolver = app(ChangesResolver::class);

    expect(array_column($resolver->rows(findActivity($fromOld))['rows'], 'new', 'field'))->toBe(['name' => 'Foo'])
        ->and(array_column($resolver->rows(findActivity($fromAttributes))['rows'], 'new', 'field'))->toBe(['name' => 'Bar']);
});

it('returns empty mode when nothing was logged', function () {
    $id = $this->logActivity(['event' => 'login', 'properties' => null]);

    expect(app(ChangesResolver::class)->rows(findActivity($id))['mode'])->toBe('empty');
});

it('masks hidden attributes', function () {
    $id = $this->logActivity([
        'event' => 'updated',
        'properties' => [
            'old' => ['password' => 'secret-old'],
            'attributes' => ['password' => 'secret-new'],
        ],
    ]);

    $resolver = app(ChangesResolver::class);

    expect($resolver->rows(findActivity($id))['rows'][0]['hidden'])->toBeTrue()
        ->and(json_encode($resolver->raw(findActivity($id))))->not->toContain('secret');
});
