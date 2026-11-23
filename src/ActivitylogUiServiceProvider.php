<?php

namespace Puzzlesoft\ActivitylogUi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Puzzlesoft\ActivitylogUi\Console\InstallCommand;
use Puzzlesoft\ActivitylogUi\Http\Middleware\Authorize;
use Puzzlesoft\ActivitylogUi\Relations\RelationResolver;
use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Support\ChangesResolver;
use Puzzlesoft\ActivitylogUi\Support\SubjectTypeResolver;
use Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver;

class ActivitylogUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/activitylog-ui.php', 'activitylog-ui');

        $this->app->scoped(ActivityTable::class);
        $this->app->scoped(UserOptionsResolver::class);
        $this->app->scoped(SubjectTypeResolver::class);
        $this->app->scoped(ChangesResolver::class);
        $this->app->scoped(RelationResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'activitylog-ui');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'activitylog-ui');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();

            $this->commands([InstallCommand::class]);
        }
    }

    protected function registerRoutes(): void
    {
        if (! config('activitylog-ui.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('activitylog-ui.route.prefix', 'activitylog'),
            'domain' => config('activitylog-ui.route.domain'),
            'middleware' => array_merge((array) config('activitylog-ui.route.middleware', ['web']), [Authorize::class]),
            'as' => 'activitylog-ui.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/activitylog-ui.php' => config_path('activitylog-ui.php'),
        ], 'activitylog-ui-config');

        $this->publishes([
            __DIR__.'/../resources/dist' => public_path('vendor/activitylog-ui'),
        ], ['activitylog-ui-assets', 'laravel-assets']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/activitylog-ui'),
        ], 'activitylog-ui-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/activitylog-ui'),
        ], 'activitylog-ui-lang');
    }
}
