<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, the routes of the package are not registered at all.
    |
    */

    'enabled' => env('ACTIVITYLOG_UI_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Access is always checked by the package's Authorize middleware, which
    | runs the callback registered with ActivitylogUi::auth(). Without a
    | callback, the UI is only reachable in the "local" environment.
    |
    */

    'route' => [
        'prefix' => env('ACTIVITYLOG_UI_PATH', 'activitylog'),
        'domain' => env('ACTIVITYLOG_UI_DOMAIN'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | "back_route" (a named Laravel route, e.g. "dashboard") or "back_url" (a
    | path, URL or route name) renders a link in the header to return to your
    | application. For dynamic per-user or per-role destinations, register
    | ActivitylogUi::backUrl(fn ($request) => ...) in AppServiceProvider.
    | Set both to null to hide the link.
    |
    */

    'title' => 'Activity Log',

    'back_route' => env('ACTIVITYLOG_UI_BACK_ROUTE'),

    'back_url' => env('ACTIVITYLOG_UI_BACK_URL', '/'),

    /*
    |--------------------------------------------------------------------------
    | Users (causer filter)
    |--------------------------------------------------------------------------
    |
    | The options of the user filter are built with the query below, unless a
    | custom resolver is registered with ActivitylogUi::users(). The resolver
    | must return an array (or collection) of [id => label].
    |
    | "model" defaults to auth.providers.users.model when null.
    | "label_column" is the ordered list of columns tried for the label; the
    | first non-empty value on each row wins. Columns missing from the table
    | are skipped. "causer_types" can list extra historical morph classes
    | (e.g. ["App\\User"]) that map to the same table.
    |
    */

    'users' => [
        'model' => null,
        'label_column' => ['name', 'nombre', 'username', 'email'],
        'causer_types' => [],
        'cache_ttl' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject types
    |--------------------------------------------------------------------------
    |
    | By default the subject types are detected from the activity log table
    | (SELECT DISTINCT subject_type). On large tables set "only" or register
    | ActivitylogUi::subjectTypes() so that scan never runs. "except" hides
    | types. Cache TTL is in seconds (0 disables the cache).
    |
    */

    'subject_types' => [
        'only' => [],
        'except' => [],
        'cache_ttl' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Relations (history page)
    |--------------------------------------------------------------------------
    |
    | The history page starts with the record itself. Registered relations
    | (ActivitylogUi::relations()) appear in a selector; choosing one loads
    | that child's activities only. "auto_discover" also offers every logged
    | model whose table has the parent's foreign key. "except" hides types.
    |
    | Living children come from their table. Deleted ones come from the
    | foreign key stored in their logs (logAll() or listed in logOnly()).
    |
    */

    'relations' => [
        'enabled' => true,
        'auto_discover' => false,
        'except' => [],
        'cache_ttl' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */

    'events' => ['created', 'updated', 'deleted', 'restored'],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | "simple" uses simplePaginate(), which avoids a COUNT(*) query. Recommended
    | for very large activity log tables.
    |
    */

    'pagination' => [
        'per_page' => 25,
        'per_page_options' => [25, 50, 100],
        'simple' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Columns used by the free text search. JSON columns are cast to text and
    | searched with LIKE. Columns that don't exist in your table are ignored
    | (e.g. "attribute_changes" only exists in spatie/laravel-activitylog v5).
    |
    */

    'search' => [
        'columns' => ['description', 'properties', 'attribute_changes'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden attributes
    |--------------------------------------------------------------------------
    |
    | Values of these attributes are masked in the detail view.
    |
    */

    'hidden_attributes' => ['password', 'remember_token'],

    /*
    |--------------------------------------------------------------------------
    | Date format
    |--------------------------------------------------------------------------
    */

    'date_format' => 'd/m/Y H:i:s',

];
