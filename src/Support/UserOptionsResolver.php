<?php

namespace Puzzlesoft\ActivitylogUi\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Cache;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;
use Throwable;

class UserOptionsResolver
{
    public const SYSTEM = 'system';

    protected ?array $options = null;

    /**
     * Memoized labels for causer IDs resolved outside options() (e.g. inactive
     * or soft-deleted users excluded by a custom ActivitylogUi::users() query,
     * or pages like /history that do not load the full user dropdown).
     *
     * @var array<int|string, string|null>
     */
    protected array $resolvedCausers = [];

    /**
     * @var array<string, array<int, string>>
     */
    protected array $candidateColumns = [];

    /**
     * @return array<int|string, string> [id => label]
     */
    public function options(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $ttl = (int) config('activitylog-ui.users.cache_ttl', 0);

        return $this->options = $ttl > 0
            ? Cache::remember('activitylog-ui.users', $ttl, fn () => $this->resolve())
            : $this->resolve();
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelClass(): ?string
    {
        return config('activitylog-ui.users.model') ?: config('auth.providers.users.model');
    }

    public function morphClass(): ?string
    {
        $class = $this->modelClass();

        return $class && class_exists($class) ? (new $class)->getMorphClass() : null;
    }

    /**
     * All causer_type values that belong to the configured User model.
     *
     * @return array<int, string>
     */
    public function causerTypes(): array
    {
        $class = $this->modelClass();
        $legacy = $class && class_basename($class) === 'User'
            ? ['App\\Models\\User', 'App\\User']
            : [];

        return array_values(array_unique(array_filter([
            $this->morphClass(),
            $class,
            ...$legacy,
            ...(array) config('activitylog-ui.users.causer_types', []),
        ])));
    }

    public function isUserType(?string $causerType): bool
    {
        return $causerType === null || in_array($causerType, $this->causerTypes(), true);
    }

    /**
     * Batch-loads labels for the causer IDs present in a list of activities so
     * views never run per-row queries and pages without the user dropdown do
     * not need to fetch the entire users table.
     */
    public function preload(iterable $activities): void
    {
        $useCustomResolver = ActivitylogUi::usersResolver() !== null;
        $known = ($this->options !== null || $useCustomResolver) ? $this->options() : [];
        $missing = [];

        foreach ($activities as $activity) {
            $id = $activity->getAttribute('causer_id');
            $type = $activity->getAttribute('causer_type');

            if ($id === null || $id === '' || ! $this->isUserType($type)) {
                continue;
            }

            if (isset($known[$id]) && trim((string) $known[$id]) !== '') {
                continue;
            }

            if (! array_key_exists($id, $this->resolvedCausers)) {
                $missing[(string) $id] = $id;
            }
        }

        if ($missing !== []) {
            $this->fetchCausersByIds(array_values($missing));
        }
    }

    /**
     * Human readable label for the causer of an activity.
     */
    public function label(?string $causerType, int|string|null $causerId): string
    {
        if ($causerId === null || $causerId === '') {
            return __('activitylog-ui::ui.system');
        }

        if ($this->isUserType($causerType)) {
            if ($this->options !== null || ActivitylogUi::usersResolver() !== null) {
                $options = $this->options();

                if (isset($options[$causerId]) && trim((string) $options[$causerId]) !== '') {
                    return (string) $options[$causerId];
                }
            }

            if (! array_key_exists($causerId, $this->resolvedCausers)) {
                $this->fetchCausersByIds([$causerId]);
            }

            if (isset($this->resolvedCausers[$causerId]) && $this->resolvedCausers[$causerId] !== '') {
                return $this->resolvedCausers[$causerId];
            }
        }

        return ($causerType ? class_basename($causerType) : 'User').' #'.$causerId;
    }

    public function flush(): void
    {
        $this->options = null;
        $this->resolvedCausers = [];
        $this->candidateColumns = [];
    }

    protected function resolve(): array
    {
        $resolver = ActivitylogUi::usersResolver();

        $result = $resolver ? call_user_func($resolver) : $this->defaultQuery();

        return $this->normalize($result);
    }

    protected function defaultQuery(): array
    {
        $class = $this->modelClass();

        if (! $class || ! class_exists($class)) {
            return [];
        }

        $model = new $class;
        $keyName = $model->getKeyName();
        $columns = $this->labelColumns($model);
        $primary = $columns[0];

        $rows = $model->newQueryWithoutScopes()
            ->toBase()
            ->orderBy($primary)
            ->get(array_values(array_unique([$keyName, ...$columns])));

        $options = [];

        foreach ($rows as $row) {
            $options[$row->{$keyName}] = $this->extractLabel($row, $columns, $keyName);
        }

        return $options;
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    protected function fetchCausersByIds(array $ids): void
    {
        foreach ($ids as $id) {
            $this->resolvedCausers[$id] = null;
        }

        $class = $this->modelClass();

        if (! $class || ! class_exists($class)) {
            return;
        }

        try {
            $model = new $class;
            $keyName = $model->getKeyName();
            $columns = $this->labelColumns($model);

            $rows = $model->newQueryWithoutScopes()
                ->toBase()
                ->whereIn($keyName, $ids)
                ->get(array_values(array_unique([$keyName, ...$columns])));

            foreach ($rows as $row) {
                $id = $row->{$keyName};
                $label = $this->extractLabel($row, $columns, $keyName);
                $this->resolvedCausers[$id] = $label;
                $this->resolvedCausers[(string) $id] = $label;
            }
        } catch (Throwable) {
            // Keep null entries so the fallback label is used without retrying.
        }
    }

    /**
     * Columns configured in users.label_column, in the order they should be tried.
     * A string is accepted so a previously published config keeps working.
     *
     * @return array<int, string>
     */
    protected function configuredLabelColumns(): array
    {
        $configured = config('activitylog-ui.users.label_column', []);
        $columns = is_array($configured) ? $configured : [$configured];

        return array_values(array_unique(array_filter(
            array_map(fn ($column) => trim((string) $column), $columns),
            fn ($column) => $column !== '',
        )));
    }

    /**
     * Ordered list of existing candidate label columns on the user table.
     *
     * @return array<int, string>
     */
    protected function labelColumns(Model $model): array
    {
        $class = $model::class;

        if (isset($this->candidateColumns[$class])) {
            return $this->candidateColumns[$class];
        }

        $actualByLower = [];
        foreach ($model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable()) as $column) {
            $actualByLower[strtolower($column)] = $column;
        }

        $candidates = $this->configuredLabelColumns();

        $found = [];
        foreach ($candidates as $candidate) {
            $lower = strtolower($candidate);
            if (isset($actualByLower[$lower])) {
                $found[] = $actualByLower[$lower];
            }
        }

        return $this->candidateColumns[$class] = $found !== [] ? $found : [$model->getKeyName()];
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function extractLabel(object $row, array $columns, string $keyName): string
    {
        foreach ($columns as $column) {
            $value = $row->{$column} ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return (string) ($row->{$keyName} ?? '');
    }

    protected function normalize(mixed $result): array
    {
        $items = $result instanceof Enumerable ? $result->all() : Arr::wrap($result);
        $candidates = $this->configuredLabelColumns();
        $options = [];

        foreach ($items as $key => $value) {
            if ($value instanceof Model) {
                $label = null;
                foreach ($candidates as $candidate) {
                    $attr = $value->getAttribute($candidate);
                    if ($attr !== null && trim((string) $attr) !== '') {
                        $label = (string) $attr;
                        break;
                    }
                }
                $options[$value->getKey()] = $label ?? (string) $value->getKey();

                continue;
            }

            $options[$key] = (string) $value;
        }

        return $options;
    }
}
