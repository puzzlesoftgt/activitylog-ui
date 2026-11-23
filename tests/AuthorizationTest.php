<?php

use Puzzlesoft\ActivitylogUi\ActivitylogUi;

it('allows access in the local environment without a callback', function () {
    ActivitylogUi::auth(null);
    app()->detectEnvironment(fn () => 'local');

    $this->get('/activitylog')->assertOk();
});

it('denies access outside local without a callback', function () {
    ActivitylogUi::auth(null);
    app()->detectEnvironment(fn () => 'production');

    $this->get('/activitylog')->assertForbidden();
});

it('uses the registered callback', function (bool $allowed, int $status) {
    app()->detectEnvironment(fn () => 'production');
    ActivitylogUi::auth(fn ($request) => $allowed);

    $this->get('/activitylog')->assertStatus($status);
})->with([
    'allowed' => [true, 200],
    'denied' => [false, 403],
]);

it('passes the request to the callback', function () {
    ActivitylogUi::auth(fn ($request) => $request->header('X-Admin') === 'yes');

    $this->get('/activitylog', ['X-Admin' => 'yes'])->assertOk();
    $this->get('/activitylog')->assertForbidden();
});

it('protects the detail view and the assets', function () {
    $id = $this->logActivity();
    ActivitylogUi::auth(fn () => false);

    $this->get("/activitylog/{$id}")->assertForbidden();
    $this->get('/activitylog/assets/app.css')->assertForbidden();
});
