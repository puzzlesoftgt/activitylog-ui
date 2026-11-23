<?php

namespace Puzzlesoft\ActivitylogUi;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ActivitylogUi
{
    protected static ?Closure $authUsing = null;

    protected static ?Closure $backUrlUsing = null;

    protected static ?Closure $usersUsing = null;

    protected static ?Closure $subjectTypesUsing = null;

    /**
     * @var array<string, string>
     */
    protected static array $labels = [];

    /**
     * @var array<string, array<string, array{foreign_key: string|false|null, label: string|null}>>
     */
    protected static array $relations = [];

    /**
     * Register the callback that decides who can access the UI.
     *
     * Without a callback, access is only granted in the "local" environment.
     */
    public static function auth(?Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Register the callback that resolves the "Back to app" URL per request
     * (for example, depending on the authenticated user or their role).
     * Returning null hides the link. A route name, path or full URL may be returned.
     */
    public static function backUrl(?Closure $callback): void
    {
        static::$backUrlUsing = $callback;
    }

    /**
     * Register the resolver for the options of the user filter.
     *
     * It must return an array or collection of [id => label], or a collection
     * of user models.
     */
    public static function users(?Closure $callback): void
    {
        static::$usersUsing = $callback;
    }

    /**
     * Register the resolver for the options of the subject type filter.
     *
     * It must return a list of subject types (class names or morph aliases)
     * or an array of [subject_type => label].
     */
    public static function subjectTypes(?Closure $callback): void
    {
        static::$subjectTypesUsing = $callback;
    }

    /**
     * Register the child models whose activities are shown in the history of
     * a parent model. A string value is the foreign key, null (or a bare class)
     * uses the convention ($parent->getForeignKey()) and false excludes an
     * auto-discovered child. An array sets the foreign key and the name shown
     * in the selector.
     *
     *     ActivitylogUi::relations(Poliza::class, [
     *         PolizaCobertura::class => ['label' => 'Coberturas'],
     *         PolizaDocumento::class => ['foreign_key' => 'poliza_id', 'label' => 'Documentos'],
     *         PolizaGuia::class,
     *         Bitacora::class => false,
     *     ]);
     *
     * @param  array<int|string, string|false|null|array{foreign_key?: string|false|null, label?: string|null}>  $children
     */
    public static function relations(string $parent, array $children): void
    {
        foreach ($children as $key => $value) {
            if (is_int($key)) {
                $child = $value;
                $foreignKey = null;
                $label = null;
            } elseif (is_array($value)) {
                $child = $key;
                $foreignKey = $value['foreign_key'] ?? null;
                $label = isset($value['label']) ? trim((string) $value['label']) : null;
                $label = $label === '' ? null : $label;
            } else {
                $child = $key;
                $foreignKey = $value;
                $label = null;
            }

            static::$relations[$parent][(string) $child] = [
                'foreign_key' => $foreignKey === true ? null : $foreignKey,
                'label' => $label,
            ];

            if ($label !== null) {
                static::label((string) $child, $label);
            }
        }
    }

    /**
     * Name shown for a model in the subject filter, the activity table and
     * the history title. Relation labels are registered here too.
     */
    public static function label(string $type, string $name): void
    {
        $name = trim($name);

        if ($name !== '') {
            static::$labels[$type] = $name;
        }
    }

    public static function registeredLabel(string $type): ?string
    {
        $label = static::$labels[$type] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /**
     * @return array<string, array<string, array{foreign_key: string|false|null, label: string|null}>>
     */
    public static function registeredRelations(): array
    {
        return static::$relations;
    }

    public static function check(Request $request): bool
    {
        if (static::$authUsing) {
            return (bool) call_user_func(static::$authUsing, $request);
        }

        return app()->environment('local');
    }

    /**
     * Resolved URL for the "Back to app" header link, or null to hide it.
     */
    public static function resolveBackUrl(Request $request): ?string
    {
        if (static::$backUrlUsing) {
            return static::normalizeTarget(call_user_func(static::$backUrlUsing, $request));
        }

        $route = config('activitylog-ui.back_route');

        if (is_string($route) && trim($route) !== '') {
            return Route::has($route) ? route($route) : null;
        }

        return static::normalizeTarget(config('activitylog-ui.back_url'));
    }

    public static function usersResolver(): ?Closure
    {
        return static::$usersUsing;
    }

    public static function subjectTypesResolver(): ?Closure
    {
        return static::$subjectTypesUsing;
    }

    public static function flush(): void
    {
        static::$authUsing = null;
        static::$backUrlUsing = null;
        static::$usersUsing = null;
        static::$subjectTypesUsing = null;
        static::$relations = [];
        static::$labels = [];
    }

    protected static function normalizeTarget(mixed $target): ?string
    {
        if (! is_string($target)) {
            return null;
        }

        $target = trim($target);

        if ($target === '') {
            return null;
        }

        if (! str_starts_with($target, '/') && ! str_contains($target, '://') && Route::has($target)) {
            return route($target);
        }

        return url($target);
    }
}
