<?php

namespace Puzzlesoft\ActivitylogUi\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes the changes stored by spatie/laravel-activitylog:
 *  - v4 stores them in properties.old / properties.attributes
 *  - v5 stores them in the attribute_changes column
 */
class ChangesResolver
{
    public const MODE_DIFF = 'diff';

    public const MODE_VALUES = 'values';

    public const MODE_EMPTY = 'empty';

    public function __construct(protected ActivityTable $table) {}

    /**
     * @return array{old: array, attributes: array, extra: array}
     */
    public function resolve(Model $activity): array
    {
        $properties = $this->toArray($activity->getAttributes()['properties'] ?? null);
        $changes = $this->table->hasColumn('attribute_changes')
            ? $this->toArray($activity->getAttributes()['attribute_changes'] ?? null)
            : [];

        $usesProperties = $changes === [];

        if ($usesProperties) {
            $changes = $properties;
        }

        return [
            'old' => $this->toArray($changes['old'] ?? []),
            'attributes' => $this->toArray($changes['attributes'] ?? []),
            'extra' => $usesProperties
                ? array_diff_key($properties, array_flip(['old', 'attributes']))
                : $properties,
        ];
    }

    /**
     * Rows ready to be rendered in the detail table.
     *
     * @return array{mode: string, rows: array<int, array{field: string, old: mixed, new: mixed, changed: bool, hidden: bool}>}
     */
    public function rows(Model $activity): array
    {
        $changes = $this->resolve($activity);
        $old = $changes['old'];
        $new = $changes['attributes'];
        $event = $activity->getAttribute('event');

        if ($event === 'created') {
            return $this->values($new ?: $old);
        }

        if ($event === 'deleted') {
            return $this->values($old ?: $new);
        }

        if ($event === 'updated' || ($old !== [] && $new !== [])) {
            return $this->diff($old, $new);
        }

        return $this->values($new ?: $old);
    }

    public function isHidden(string $field): bool
    {
        $hidden = array_map('strtolower', (array) config('activitylog-ui.hidden_attributes', []));

        return in_array(strtolower($field), $hidden, true)
            || in_array(strtolower((string) last(explode('.', $field))), $hidden, true);
    }

    /**
     * Decoded raw JSON columns with hidden attributes masked.
     *
     * @return array<string, array>
     */
    public function raw(Model $activity): array
    {
        $raw = ['properties' => $this->toArray($activity->getAttributes()['properties'] ?? null)];

        if ($this->table->hasColumn('attribute_changes')) {
            $raw['attribute_changes'] = $this->toArray($activity->getAttributes()['attribute_changes'] ?? null);
        }

        return array_filter(array_map(fn (array $value) => $this->mask($value), $raw));
    }

    protected function maskNested(mixed $value): mixed
    {
        return is_array($value) ? $this->mask($value) : $value;
    }

    public function mask(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isHidden($key)) {
                $values[$key] = '********';
            } elseif (is_array($value)) {
                $values[$key] = $this->mask($value);
            }
        }

        return $values;
    }

    protected function diff(array $old, array $new): array
    {
        $fields = array_keys($new + $old);

        if ($fields === []) {
            return ['mode' => self::MODE_EMPTY, 'rows' => []];
        }

        $rows = [];
        foreach ($fields as $field) {
            $before = $this->maskNested($old[$field] ?? null);
            $after = $this->maskNested($new[$field] ?? null);

            $rows[] = [
                'field' => (string) $field,
                'old' => $before,
                'new' => $after,
                'changed' => $before !== $after,
                'hidden' => $this->isHidden((string) $field),
            ];
        }

        return ['mode' => self::MODE_DIFF, 'rows' => $rows];
    }

    protected function values(array $values): array
    {
        if ($values === []) {
            return ['mode' => self::MODE_EMPTY, 'rows' => []];
        }

        $rows = [];
        foreach ($values as $field => $value) {
            $rows[] = [
                'field' => (string) $field,
                'old' => null,
                'new' => $this->maskNested($value),
                'changed' => false,
                'hidden' => $this->isHidden((string) $field),
            ];
        }

        return ['mode' => self::MODE_VALUES, 'rows' => $rows];
    }

    protected function toArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return (array) $value;
    }
}
