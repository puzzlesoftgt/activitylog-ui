<?php

namespace Puzzlesoft\ActivitylogUi\Relations;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;
use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Support\JsonSql;
use Puzzlesoft\ActivitylogUi\Support\SubjectTypeResolver;
use Throwable;

/**
 * Resolves the direct children of a logged model and the activities of
 * those children, including children that no longer exist.
 */
class RelationResolver
{
    public const SELF = 'self';

    /**
     * @var array<string, array{current: array<int, string>, historical: array<int, string>, deleted: array<int, string>}>
     */
    protected array $ids = [];

    /**
     * @var array<string, array<string, array<string, bool>>>
     */
    protected array $modelColumns = [];

    public function __construct(
        protected ActivityTable $table,
        protected SubjectTypeResolver $subjectTypes,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('activitylog-ui.relations.enabled', true);
    }

    /**
     * @return array<string, array{type: string, class: string, foreign_key: string, label: string}> keyed by child subject type
     */
    public function definitions(string $parentType): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $parentClass = $this->subjectTypes->className($parentType);
        $foreignKey = $this->conventionForeignKey($parentClass);
        $definitions = [];

        if (config('activitylog-ui.relations.auto_discover', false)) {
            foreach ($this->discover($parentType, $parentClass, $foreignKey) as $type) {
                $definitions[$type] = ['foreign_key' => $foreignKey, 'label' => null];
            }
        }

        foreach ($this->registered($parentType, $parentClass) as $child => $spec) {
            $type = $this->storedType($child);
            $key = $spec['foreign_key'];

            if ($key === false) {
                unset($definitions[$type], $definitions[$child]);

                continue;
            }

            $definitions[$type] = [
                'foreign_key' => $key ?? $foreignKey,
                'label' => $spec['label'],
            ];
        }

        $except = (array) config('activitylog-ui.relations.except', []);
        $result = [];

        foreach ($definitions as $type => $spec) {
            $class = $this->subjectTypes->className($type);
            $key = $spec['foreign_key'];

            if ($type === $parentType || $class === $parentClass || ! JsonSql::isSafeKey((string) $key)) {
                continue;
            }

            if (in_array($type, $except, true) || in_array($class, $except, true)) {
                continue;
            }

            $label = $spec['label'] ?: $this->subjectTypes->shortName($type);

            $result[$type] = [
                'type' => $type,
                'class' => $class,
                'foreign_key' => (string) $key,
                'label' => $label,
            ];
        }

        uasort($result, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $result;
    }

    /**
     * IDs of the children of a parent record.
     *
     *  - current: children that exist today with the foreign key
     *  - historical: children whose logs stored the foreign key
     *  - deleted: historical children that are no longer linked in the child table
     *
     * @return array{current: array<int, string>, historical: array<int, string>, deleted: array<int, string>}
     */
    public function childIds(array $definition, string $parentId): array
    {
        $cacheKey = $definition['type'].'|'.$definition['foreign_key'].'|'.$parentId;

        if (isset($this->ids[$cacheKey])) {
            return $this->ids[$cacheKey];
        }

        $current = $this->currentIds($definition, $parentId);
        $historical = $this->historicalIds($definition, $parentId);

        return $this->ids[$cacheKey] = [
            'current' => $current,
            'historical' => $historical,
            'deleted' => array_values(array_diff($historical, $current)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allChildIds(array $definition, string $parentId): array
    {
        $ids = $this->childIds($definition, $parentId);

        return array_values(array_unique(array_merge($ids['current'], $ids['historical'])));
    }

    /**
     * Activities of the record itself, or of one registered child relation.
     *
     * @param  string|null  $relation  null or "self" for the record, or a child subject type
     */
    public function query(string $type, string $id, ?string $relation = null): Builder
    {
        if ($relation === null || $relation === self::SELF) {
            return $this->table->query()->where('subject_type', $type)->where('subject_id', $id);
        }

        $definition = $this->definitions($type)[$relation] ?? null;
        $ids = $definition ? $this->allChildIds($definition, $id) : [];

        if ($definition === null || $ids === []) {
            return $this->table->query()->whereRaw('1 = 0');
        }

        return $this->table->query()
            ->where('subject_type', $definition['type'])
            ->whereIn('subject_id', $ids);
    }

    public function flush(): void
    {
        $this->ids = [];
        $this->modelColumns = [];
    }

    protected function conventionForeignKey(string $parentClass): string
    {
        if ($this->isModel($parentClass)) {
            return (new $parentClass)->getForeignKey();
        }

        return Str::snake(class_basename($parentClass)).'_id';
    }

    /**
     * Logged subject types whose table has the foreign key column.
     *
     * @return array<int, string>
     */
    protected function discover(string $parentType, string $parentClass, string $foreignKey): array
    {
        $needle = strtolower($foreignKey);
        $types = [];

        foreach ($this->loggedModelColumns() as $type => $columns) {
            $class = $this->subjectTypes->className($type);

            if ($type === $parentType || $class === $parentClass) {
                continue;
            }

            if (isset($columns[$needle])) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Column sets (lowercased) for all logged Eloquent models, fetched in a
     * single batched schema query per connection instead of one query per model.
     *
     * @return array<string, array<string, bool>>
     */
    protected function loggedModelColumns(): array
    {
        $loggedTypes = $this->subjectTypes->loggedTypes();

        if ($loggedTypes === []) {
            return [];
        }

        $ttl = (int) config('activitylog-ui.relations.cache_ttl', 600);
        $cacheKey = 'activitylog-ui.model_columns.'.md5(implode('|', $loggedTypes));

        if (isset($this->modelColumns[$cacheKey])) {
            return $this->modelColumns[$cacheKey];
        }

        $resolve = function () use ($loggedTypes) {
            $byConnection = [];

            foreach ($loggedTypes as $type) {
                $class = $this->subjectTypes->className($type);

                if (! $this->isModel($class)) {
                    continue;
                }

                try {
                    $model = new $class;
                    $connection = $model->getConnection();
                    $connName = $model->getConnectionName() ?? (string) config('database.default', 'default');

                    $byConnection[$connName][] = [
                        'type' => $type,
                        'connection' => $connection,
                        'raw_table' => $model->getTable(),
                        'full_table' => $connection->getTablePrefix().$model->getTable(),
                    ];
                } catch (Throwable) {
                    continue;
                }
            }

            $columnsByType = [];

            foreach ($byConnection as $entries) {
                $tableColumns = $this->fetchTablesColumns($entries[0]['connection'], $entries);

                foreach ($entries as $entry) {
                    $cols = $tableColumns[strtolower($entry['full_table'])] ?? [];
                    $columnsByType[$entry['type']] = array_fill_keys($cols, true);
                }
            }

            return $columnsByType;
        };

        return $this->modelColumns[$cacheKey] = $ttl > 0
            ? Cache::remember($cacheKey, $ttl, $resolve)
            : $resolve();
    }

    /**
     * @param  array<int, array{type: string, connection: ConnectionInterface, raw_table: string, full_table: string}>  $entries
     * @return array<string, array<int, string>>
     */
    protected function fetchTablesColumns(ConnectionInterface $connection, array $entries): array
    {
        $fullTables = array_values(array_unique(array_column($entries, 'full_table')));
        $hasQualifiedName = array_filter($fullTables, fn (string $table) => str_contains($table, '.')) !== [];

        if (! $hasQualifiedName) {
            $driver = $connection->getDriverName();
            $placeholders = implode(', ', array_fill(0, count($fullTables), '?'));

            try {
                $rows = match ($driver) {
                    'mysql', 'mariadb' => $connection->select(
                        "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = ? AND table_name IN ({$placeholders})",
                        [$connection->getDatabaseName(), ...$fullTables]
                    ),
                    'pgsql' => $connection->select(
                        "SELECT table_name, column_name FROM information_schema.columns WHERE table_catalog = ? AND table_schema = current_schema() AND table_name IN ({$placeholders})",
                        [$connection->getDatabaseName(), ...$fullTables]
                    ),
                    'sqlsrv' => $connection->select(
                        "SELECT table_name, column_name FROM information_schema.columns WHERE table_catalog = ? AND table_name IN ({$placeholders})",
                        [$connection->getDatabaseName(), ...$fullTables]
                    ),
                    default => null,
                };

                if ($rows !== null) {
                    $result = [];

                    foreach ($rows as $row) {
                        $data = array_change_key_case((array) $row, CASE_LOWER);

                        if (isset($data['table_name'], $data['column_name'])) {
                            $result[strtolower((string) $data['table_name'])][] = strtolower((string) $data['column_name']);
                        }
                    }

                    return $result;
                }
            } catch (Throwable) {
                // Fall back to the schema builder below when information_schema is unavailable.
            }
        }

        $schema = $connection->getSchemaBuilder();
        $result = [];
        $seen = [];

        foreach ($entries as $entry) {
            $key = strtolower($entry['full_table']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            try {
                $result[$key] = array_map('strtolower', $schema->getColumnListing($entry['raw_table']));
            } catch (Throwable) {
                $result[$key] = [];
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{foreign_key: string|false|null, label: string|null}>
     */
    protected function registered(string $parentType, string $parentClass): array
    {
        $registered = [];

        foreach (ActivitylogUi::registeredRelations() as $parent => $children) {
            if ($parent === $parentType || $parent === $parentClass || $this->storedType($parent) === $parentType) {
                $registered = array_merge($registered, $children);
            }
        }

        return $registered;
    }

    /**
     * The subject_type value stored in the logs for a class (morph alias aware).
     */
    protected function storedType(string $type): string
    {
        if ($this->isModel($type)) {
            try {
                return (new $type)->getMorphClass();
            } catch (Throwable) {
                return $type;
            }
        }

        return $type;
    }

    /**
     * @return array<int, string>
     */
    protected function currentIds(array $definition, string $parentId): array
    {
        if (! $this->isModel($definition['class'])) {
            return [];
        }

        try {
            $model = new $definition['class'];

            return $model->newQueryWithoutScopes()
                ->where($definition['foreign_key'], $parentId)
                ->pluck($model->getKeyName())
                ->map(fn ($id) => (string) $id)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    protected function historicalIds(array $definition, string $parentId): array
    {
        $columns = array_values(array_filter(
            ['properties', 'attribute_changes'],
            fn ($column) => $this->table->hasColumn($column)
        ));

        if ($columns === []) {
            return [];
        }

        $query = $this->table->query()->toBase();
        $grammar = $query->getGrammar();
        $driver = $this->table->driver();

        return $query
            ->where('subject_type', $definition['type'])
            ->where(function ($where) use ($columns, $grammar, $driver, $definition, $parentId) {
                foreach ($columns as $column) {
                    foreach (['attributes', 'old'] as $section) {
                        $expression = JsonSql::value($grammar->wrap($column), [$section, $definition['foreign_key']], $driver);
                        $where->orWhereRaw("{$expression} = ?", [(string) $parentId]);
                    }
                }
            })
            ->distinct()
            ->pluck('subject_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    protected function isModel(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, Model::class);
    }
}
