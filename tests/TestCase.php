<?php

namespace Puzzlesoft\ActivitylogUi\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;
use Puzzlesoft\ActivitylogUi\ActivitylogUiServiceProvider;
use Puzzlesoft\ActivitylogUi\Tests\Fixtures\User;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        ActivitylogUi::flush();
        ActivitylogUi::auth(fn () => true);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->timestamps();
        });

        $this->createActivityTable(withAttributeChanges: true);
    }

    protected function tearDown(): void
    {
        ActivitylogUi::flush();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActivitylogServiceProvider::class,
            ActivitylogUiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('activitylog-ui.users.label_column', 'username');
        $app['config']->set('activitylog-ui.subject_types.cache_ttl', 0);
        $app['config']->set('app.timezone', 'UTC');
    }

    /**
     * v4 tables have no attribute_changes column, v5 tables do.
     */
    protected function createActivityTable(bool $withAttributeChanges): void
    {
        Schema::dropIfExists('activity_log');

        Schema::create('activity_log', function (Blueprint $table) use ($withAttributeChanges) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            if ($withAttributeChanges) {
                $table->json('attribute_changes')->nullable();
            }
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });

        app()->forgetScopedInstances();
    }

    protected function logActivity(array $attributes = []): int
    {
        $attributes += [
            'log_name' => 'default',
            'description' => 'updated',
            'subject_type' => 'App\\Models\\Post',
            'subject_id' => 1,
            'event' => 'updated',
            'causer_type' => User::class,
            'causer_id' => null,
            'properties' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach (['properties', 'attribute_changes'] as $json) {
            if (isset($attributes[$json]) && is_array($attributes[$json])) {
                $attributes[$json] = json_encode($attributes[$json]);
            }
        }

        return DB::table('activity_log')->insertGetId($attributes);
    }
}
