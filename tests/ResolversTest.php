<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;
use Puzzlesoft\ActivitylogUi\Support\SubjectTypeResolver;
use Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver;
use Puzzlesoft\ActivitylogUi\Tests\Fixtures\User;

it('builds user options from the configured label column', function () {
    $zoe = User::create(['username' => 'zoe']);
    $ana = User::create(['username' => 'ana']);

    expect(app(UserOptionsResolver::class)->options())->toBe([$ana->id => 'ana', $zoe->id => 'zoe']);
});

it('uses the first existing column from the configured list', function () {
    config()->set('activitylog-ui.users.label_column', ['name', 'username']);
    $user = User::create(['username' => 'ana']);

    expect(app(UserOptionsResolver::class)->options())->toBe([$user->id => 'ana']);
});

it('does not label users with a column that is not configured', function () {
    config()->set('activitylog-ui.users.label_column', ['name']);
    $user = User::create(['username' => 'ana']);

    expect(app(UserOptionsResolver::class)->options())->toBe([$user->id => (string) $user->id]);
});

it('uses a custom users resolver', function () {
    User::create(['username' => 'ana']);

    ActivitylogUi::users(fn () => User::orderBy('username')->pluck('username', 'id')->map(fn ($name) => strtoupper($name)));

    expect(app(UserOptionsResolver::class)->options())->toBe([1 => 'ANA']);
});

it('accepts a collection of models from the users resolver', function () {
    $user = User::create(['username' => 'ana']);

    ActivitylogUi::users(fn () => User::all());

    expect(app(UserOptionsResolver::class)->options())->toBe([$user->id => 'ana']);
});

it('labels causers', function () {
    $user = User::create(['username' => 'ana']);
    $resolver = app(UserOptionsResolver::class);

    expect($resolver->label(User::class, $user->id))->toBe('ana')
        ->and($resolver->label('App\\User', $user->id))->toBe('ana')
        ->and($resolver->label(null, null))->toBe('System')
        ->and($resolver->label(User::class, 999))->toBe('User #999')
        ->and($resolver->label('App\\Models\\Admin', 5))->toBe('Admin #5');
});

it('resolves causers excluded from a custom users filter query', function () {
    $active = User::create(['username' => 'activa']);
    $inactive = User::create(['username' => 'inactiva']);

    ActivitylogUi::users(fn () => [$active->id => 'activa']);

    $resolver = app(UserOptionsResolver::class);

    expect($resolver->options())->toBe([$active->id => 'activa'])
        ->and($resolver->label(User::class, $inactive->id))->toBe('inactiva');
});

it('detects subject types from the activity table', function () {
    $this->logActivity(['subject_type' => 'App\\Models\\Post']);
    $this->logActivity(['subject_type' => 'App\\Models\\Post']);
    $this->logActivity(['subject_type' => 'App\\Models\\Order']);
    $this->logActivity(['subject_type' => null, 'subject_id' => null]);

    $options = app(SubjectTypeResolver::class)->options();

    expect(array_keys($options))->toBe(['App\\Models\\Order', 'App\\Models\\Post'])
        ->and($options['App\\Models\\Post']['label'])->toBe('Post');
});

it('uses the full class name when short names collide', function () {
    $this->logActivity(['subject_type' => 'App\\Models\\Post']);
    $this->logActivity(['subject_type' => 'Blog\\Models\\Post']);

    $options = app(SubjectTypeResolver::class)->options();

    expect($options['App\\Models\\Post']['label'])->toBe('App\\Models\\Post')
        ->and($options['Blog\\Models\\Post']['label'])->toBe('Blog\\Models\\Post');
});

it('resolves morph map aliases', function () {
    Relation::morphMap(['post' => 'App\\Models\\BlogPost']);
    $this->logActivity(['subject_type' => 'post']);

    $options = app(SubjectTypeResolver::class)->options();

    expect($options['post']['label'])->toBe('BlogPost')
        ->and($options['post']['title'])->toBe('post (App\\Models\\BlogPost)');

    Relation::morphMap([], false);
});

it('uses registered names for subject types and parents', function () {
    ActivitylogUi::relations(\Puzzlesoft\ActivitylogUi\Tests\Fixtures\Actividad::class, [
        \Puzzlesoft\ActivitylogUi\Tests\Fixtures\ActividadEstudiante::class => ['label' => 'Estudiantes'],
    ]);
    ActivitylogUi::label(\Puzzlesoft\ActivitylogUi\Tests\Fixtures\Actividad::class, 'Tareas');

    $this->logActivity(['subject_type' => \Puzzlesoft\ActivitylogUi\Tests\Fixtures\ActividadEstudiante::class]);

    $resolver = app(\Puzzlesoft\ActivitylogUi\Support\SubjectTypeResolver::class);

    expect($resolver->label(\Puzzlesoft\ActivitylogUi\Tests\Fixtures\ActividadEstudiante::class))->toBe('Estudiantes')
        ->and($resolver->label(\Puzzlesoft\ActivitylogUi\Tests\Fixtures\Actividad::class))->toBe('Tareas')
        ->and($resolver->options()[\Puzzlesoft\ActivitylogUi\Tests\Fixtures\ActividadEstudiante::class]['label'])->toBe('Estudiantes');
});

it('supports custom subject types and exclusions', function () {
    ActivitylogUi::subjectTypes(fn () => ['App\\Models\\Post', 'App\\Models\\Secret', 'App\\Models\\Order' => 'Pedidos']);
    config()->set('activitylog-ui.subject_types.except', ['App\\Models\\Secret']);

    $options = app(SubjectTypeResolver::class)->options();

    expect(array_column($options, 'label'))->toBe(['Pedidos', 'Post']);
});
