<?php

use Puzzlesoft\ActivitylogUi\Tests\Fixtures\User;

it('renders the index with filters and activities', function () {
    $user = User::create(['username' => 'krystel']);
    $this->logActivity([
        'subject_type' => 'App\\Models\\PolizaRequerimiento',
        'subject_id' => 92694,
        'causer_id' => $user->id,
    ]);

    $this->get('/activitylog')
        ->assertOk()
        ->assertSee('PolizaRequerimiento')
        ->assertSee('#92694')
        ->assertSee('krystel')
        ->assertSee('activitylog-filters', false);
});

it('renders the empty state', function () {
    $this->get('/activitylog?search=nothing')
        ->assertOk()
        ->assertSee('No activities found');
});

it('supports simple pagination', function () {
    config()->set('activitylog-ui.pagination.simple', true);
    $this->logActivity();

    $this->get('/activitylog')->assertOk();
});

it('renders the diff table for updated activities', function () {
    $id = $this->logActivity([
        'event' => 'updated',
        'attribute_changes' => [
            'old' => ['estado' => 'N', 'pago_pendiente' => 800],
            'attributes' => ['estado' => 'C', 'pago_pendiente' => 0],
        ],
    ]);

    $this->get("/activitylog/{$id}")
        ->assertOk()
        ->assertSee('Old value')
        ->assertSee('New value')
        ->assertSee('pago_pendiente')
        ->assertSee('800');
});

it('renders the values table for created activities', function () {
    $id = $this->logActivity([
        'event' => 'created',
        'properties' => ['attributes' => ['nombre' => 'Póliza nueva']],
    ]);

    $this->get("/activitylog/{$id}")
        ->assertOk()
        ->assertSee('Data when created')
        ->assertSee('Póliza nueva')
        ->assertDontSee('Old value');
});

it('keeps the filters in the back link', function () {
    $id = $this->logActivity();

    $this->get("/activitylog/{$id}?causer=system&page=3")
        ->assertOk()
        ->assertSee('/activitylog?causer=system&amp;page=3', false);
});

it('returns 404 for unknown activities', function () {
    $this->get('/activitylog/999')->assertNotFound();
});

it('serves the compiled assets', function () {
    $this->get('/activitylog/assets/app.css')->assertOk();
    $this->get('/activitylog/assets/app.js')->assertOk();
    $this->get('/activitylog/assets/other.js')->assertNotFound();
});

it('links activities that share a batch', function () {
    $batch = '11111111-1111-1111-1111-111111111111';
    $first = $this->logActivity(['batch_uuid' => $batch, 'subject_type' => 'App\\Models\\Post', 'subject_id' => 1]);
    $this->logActivity(['batch_uuid' => $batch, 'subject_type' => 'App\\Models\\Order', 'subject_id' => 2, 'event' => 'created']);

    $this->get('/activitylog?batch='.$batch)
        ->assertOk()
        ->assertSee('View batch')
        ->assertSee('Same batch')
        ->assertSee('Order')
        ->assertSee('#2');

    $this->get("/activitylog/{$first}")
        ->assertOk()
        ->assertSee('Order #2')
        ->assertSee('created');
});

it('renders translations in spanish', function () {
    app()->setLocale('es');
    $id = $this->logActivity(['event' => 'updated', 'properties' => ['old' => ['a' => 1], 'attributes' => ['a' => 2]]]);

    $this->get("/activitylog/{$id}")
        ->assertOk()
        ->assertSee('Valor anterior')
        ->assertSee('Valor nuevo');
});

it('resolves back_to_app from a named route, url or callback', function () {
    \Illuminate\Support\Facades\Route::get('/mi-panel', fn () => 'ok')->name('panel.home');

    config()->set('activitylog-ui.back_route', 'panel.home');
    $this->get('/activitylog')->assertOk()->assertSee('/mi-panel', false);

    \Puzzlesoft\ActivitylogUi\ActivitylogUi::backUrl(fn ($request) => $request->query('rol') === 'admin' ? 'panel.home' : '/inicio-maestro');
    $this->get('/activitylog?rol=admin')->assertOk()->assertSee('/mi-panel', false);
    $this->get('/activitylog?rol=maestro')->assertOk()->assertSee('/inicio-maestro', false);

    \Puzzlesoft\ActivitylogUi\ActivitylogUi::backUrl(fn () => null);
    $this->get('/activitylog')->assertOk()->assertDontSee('Back to app');
});

it('falls back to the packaged asset route when published assets are stale', function () {
    $dir = public_path('vendor/activitylog-ui');
    @mkdir($dir, 0777, true);
    $published = $dir.'/app.css';
    $packaged = dirname(__DIR__).'/resources/dist/app.css';

    try {
        file_put_contents($published, '/* stale css */');
        expect(\Puzzlesoft\ActivitylogUi\Support\Assets::url('app.css'))->toContain('/activitylog/assets/app.css');

        copy($packaged, $published);
        expect(\Puzzlesoft\ActivitylogUi\Support\Assets::url('app.css'))->toContain('/vendor/activitylog-ui/app.css');
    } finally {
        @unlink($published);
        @rmdir($dir);
    }
});
