<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;
use Puzzlesoft\ActivitylogUi\Relations\RelationResolver;
use Puzzlesoft\ActivitylogUi\Tests\Fixtures\Actividad;
use Puzzlesoft\ActivitylogUi\Tests\Fixtures\ActividadEstudiante;
use Puzzlesoft\ActivitylogUi\Tests\Fixtures\Curso;

beforeEach(function () {
    config()->set('activitylog-ui.relations.cache_ttl', 0);

    Schema::create('actividad', function (Blueprint $table) {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });

    Schema::create('actividad_estudiante', function (Blueprint $table) {
        $table->id();
        $table->foreignId('actividad_id');
        $table->decimal('nota')->nullable();
        $table->timestamps();
    });

    Schema::create('curso', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');
        $table->unsignedBigInteger('actividad_ref')->nullable();
        $table->timestamps();
    });

    $this->actividad = Actividad::create(['titulo' => 'Tarea 1']);
    $this->logActivity(['subject_type' => Actividad::class, 'subject_id' => $this->actividad->id, 'event' => 'created']);
});

function relations(): RelationResolver
{
    return app(RelationResolver::class);
}

function historyIds(string $type, string $id, ?string $relation = null): array
{
    return relations()->query($type, $id, $relation)->orderBy('id')->pluck('id')->all();
}

it('discovers children by the foreign key column', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    $this->logActivity(['subject_type' => ActividadEstudiante::class, 'subject_id' => 1]);
    $this->logActivity(['subject_type' => Curso::class, 'subject_id' => 1]);

    $definitions = relations()->definitions(Actividad::class);

    expect(array_keys($definitions))->toBe([ActividadEstudiante::class])
        ->and($definitions[ActividadEstudiante::class]['foreign_key'])->toBe('actividad_id');
});

it('shows a custom relation label in the selector and the subject column', function () {
    ActivitylogUi::relations(Actividad::class, [
        ActividadEstudiante::class => ['label' => 'Estudiantes'],
        Curso::class => 'actividad_ref',
    ]);

    $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 321, 'event' => 'deleted',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id]],
    ]);

    $definitions = relations()->definitions(Actividad::class);

    expect($definitions[ActividadEstudiante::class]['label'])->toBe('Estudiantes')
        ->and($definitions[ActividadEstudiante::class]['foreign_key'])->toBe('actividad_id')
        ->and($definitions[Curso::class]['foreign_key'])->toBe('actividad_ref')
        ->and($definitions[Curso::class]['label'])->toBe('Curso');

    $this->get('/activitylog/history?'.http_build_query([
        'subject_type' => Actividad::class,
        'subject_id' => $this->actividad->id,
        'relation' => ActividadEstudiante::class,
    ]))
        ->assertOk()
        ->assertSee('Estudiantes')
        ->assertDontSee('>ActividadEstudiante<', false);
});

it('supports explicit relations and exclusions', function () {
    $this->logActivity(['subject_type' => ActividadEstudiante::class, 'subject_id' => 1]);
    $this->logActivity(['subject_type' => Curso::class, 'subject_id' => 1]);

    ActivitylogUi::relations(Actividad::class, [
        Curso::class => 'actividad_ref',
        ActividadEstudiante::class => false,
    ]);

    $definitions = relations()->definitions(Actividad::class);

    expect(array_keys($definitions))->toBe([Curso::class])
        ->and($definitions[Curso::class]['foreign_key'])->toBe('actividad_ref');
});

it('ignores relations when disabled or excluded in config', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    $this->logActivity(['subject_type' => ActividadEstudiante::class, 'subject_id' => 1]);

    config()->set('activitylog-ui.relations.except', [ActividadEstudiante::class]);
    expect(relations()->definitions(Actividad::class))->toBe([]);

    config()->set('activitylog-ui.relations.except', []);
    config()->set('activitylog-ui.relations.enabled', false);
    expect(relations()->definitions(Actividad::class))->toBe([]);
});

it('finds deleted children through the foreign key stored in their logs', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    $id = (string) $this->actividad->id;

    // v4: deleted log stores the attributes in properties
    $deletedV4 = $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 50, 'event' => 'deleted',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id, 'nota' => 80]],
    ]);

    // v5: created log stores the attributes in attribute_changes
    $createdV5 = $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 51, 'event' => 'created',
        'attribute_changes' => ['attributes' => ['actividad_id' => $this->actividad->id]],
    ]);

    // belongs to another actividad
    $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 52, 'event' => 'created',
        'attribute_changes' => ['attributes' => ['actividad_id' => 999]],
    ]);

    $ids = relations()->childIds(relations()->definitions(Actividad::class)[ActividadEstudiante::class], $id);

    expect($ids['historical'])->toEqualCanonicalizing(['50', '51'])
        ->and($ids['deleted'])->toEqualCanonicalizing(['50', '51'])
        ->and(historyIds(Actividad::class, $id, ActividadEstudiante::class))->toBe([$deletedV4, $createdV5]);
});

it('includes the updates of deleted children', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    $id = (string) $this->actividad->id;

    $created = $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 70, 'event' => 'created',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id, 'nota' => null]],
    ]);
    $updated = $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 70, 'event' => 'updated',
        'properties' => ['old' => ['nota' => null], 'attributes' => ['nota' => 95]],
    ]);
    $deleted = $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 70, 'event' => 'deleted',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id, 'nota' => 95]],
    ]);

    expect(historyIds(Actividad::class, $id, ActividadEstudiante::class))->toBe([$created, $updated, $deleted]);
});

it('finds existing children without a created log', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    $child = ActividadEstudiante::create(['actividad_id' => $this->actividad->id]);
    $updated = $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => $child->id, 'event' => 'updated',
        'properties' => ['old' => ['nota' => null], 'attributes' => ['nota' => 70]],
    ]);

    $ids = relations()->childIds(relations()->definitions(Actividad::class)[ActividadEstudiante::class], (string) $this->actividad->id);

    expect($ids['current'])->toBe([(string) $child->id])
        ->and($ids['deleted'])->toBe([])
        ->and(historyIds(Actividad::class, (string) $this->actividad->id, ActividadEstudiante::class))->toBe([$updated]);
});

it('filters by self and loads only the chosen relation', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    $id = (string) $this->actividad->id;
    $own = $this->logActivity(['subject_type' => Actividad::class, 'subject_id' => $this->actividad->id, 'event' => 'updated']);

    $existing = ActividadEstudiante::create(['actividad_id' => $this->actividad->id]);
    $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => $existing->id, 'event' => 'created',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id]],
    ]);
    $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 500, 'event' => 'deleted',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id]],
    ]);

    $ids = relations()->childIds(relations()->definitions(Actividad::class)[ActividadEstudiante::class], $id);

    expect(historyIds(Actividad::class, $id, RelationResolver::SELF))->toHaveCount(2)->toContain($own)
        ->and(historyIds(Actividad::class, $id))->toHaveCount(2)
        ->and($ids['deleted'])->toBe(['500'])
        ->and(historyIds(Actividad::class, $id, ActividadEstudiante::class))->toHaveCount(2);
});

it('renders the chosen relation and leaves the record view alone', function () {
    ActivitylogUi::relations(Actividad::class, [ActividadEstudiante::class]);

    $this->logActivity([
        'subject_type' => ActividadEstudiante::class, 'subject_id' => 321, 'event' => 'deleted',
        'properties' => ['attributes' => ['actividad_id' => $this->actividad->id]],
    ]);

    \Illuminate\Support\Facades\DB::enableQueryLog();
    \Illuminate\Support\Facades\DB::flushQueryLog();

    $this->get('/activitylog/history?'.http_build_query(['subject_type' => Actividad::class, 'subject_id' => $this->actividad->id]))
        ->assertOk()
        ->assertSee('ActividadEstudiante')
        ->assertDontSee('#321')
        ->assertSee('This record')
        ->assertDontSee('Everything');

    expect(collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query')->implode("\n"))
        ->not->toContain('actividad_estudiante');

    $this->get('/activitylog/history?'.http_build_query([
        'subject_type' => Actividad::class,
        'subject_id' => $this->actividad->id,
        'relation' => ActividadEstudiante::class,
    ]))
        ->assertOk()
        ->assertSee('#321')
        ->assertSee('Deleted')
        ->assertSee('back=history', false);
});

it('does not repeat schema discovery queries per parent model', function () {
    config()->set('activitylog-ui.relations.auto_discover', true);
    config()->set('activitylog-ui.relations.cache_ttl', 600);
    config()->set('activitylog-ui.subject_types.cache_ttl', 600);

    $this->logActivity(['subject_type' => ActividadEstudiante::class, 'subject_id' => 1]);
    $this->logActivity(['subject_type' => Curso::class, 'subject_id' => 1]);

    // Warm discovery cache on first model
    relations()->definitions(Actividad::class);

    \Illuminate\Support\Facades\DB::enableQueryLog();
    \Illuminate\Support\Facades\DB::flushQueryLog();

    // Second parent model reuses the cached column map with zero schema queries
    app(RelationResolver::class)->definitions(Curso::class);

    expect(\Illuminate\Support\Facades\DB::getQueryLog())->toHaveCount(0);
    \Illuminate\Support\Facades\DB::disableQueryLog();
});

it('links the detail page and the index to the history', function () {
    $activityId = $this->logActivity(['subject_type' => Actividad::class, 'subject_id' => $this->actividad->id]);

    $this->get("/activitylog/{$activityId}")->assertOk()->assertSee('History with related records');

    $this->get('/activitylog?'.http_build_query(['subject_type' => Actividad::class, 'subject_id' => $this->actividad->id]))
        ->assertOk()
        ->assertSee('History with related records');

    $this->get("/activitylog/{$activityId}?back=history&subject_type=x&subject_id=1")
        ->assertOk()
        ->assertSee('/activitylog/history?subject_type=x&amp;subject_id=1', false);
});

it('requires a subject and authorization', function () {
    $this->get('/activitylog/history')->assertNotFound();

    ActivitylogUi::auth(fn () => false);
    $this->get('/activitylog/history?subject_type=a&subject_id=1')->assertForbidden();
});
