<?php

namespace Puzzlesoft\ActivitylogUi\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;

class SubjectTypeResolver
{
    protected ?array $options = null;

    public function __construct(protected ActivityTable $table) {}

    /**
     * @return array<string, array{label: string, title: string}> keyed by subject_type
     */
    public function options(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $types = $this->types();
        $except = (array) config('activitylog-ui.subject_types.except', []);

        $labels = [];
        foreach ($types as $type => $label) {
            if (in_array($type, $except, true)) {
                continue;
            }

            $labels[$type] = $label
                ?? ActivitylogUi::registeredLabel($type)
                ?? ActivitylogUi::registeredLabel($this->className($type))
                ?? $this->shortName($type);
        }

        $duplicated = array_keys(array_filter(array_count_values($labels), fn ($count) => $count > 1));

        $options = [];
        foreach ($labels as $type => $label) {
            $options[$type] = [
                'label' => in_array($label, $duplicated, true) ? $this->className($type) : $label,
                'title' => $this->title($type),
            ];
        }

        uasort($options, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $this->options = $options;
    }

    /**
     * Fully qualified class name for a subject type, resolving morph aliases.
     */
    public function className(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        return Relation::getMorphedModel($type) ?? $type;
    }

    public function shortName(?string $type): string
    {
        return class_basename($this->className($type));
    }

    /**
     * Name shown in the UI: a subjectTypes() label, then ActivitylogUi::label()
     * (including relation labels), then the short class name.
     */
    public function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        $custom = $this->customLabel($type);

        return $custom
            ?? ActivitylogUi::registeredLabel($type)
            ?? ActivitylogUi::registeredLabel($this->className($type))
            ?? $this->shortName($type);
    }

    public function title(?string $type): string
    {
        $class = $this->className($type);

        return $class !== $type ? $type.' ('.$class.')' : (string) $type;
    }

    public function flush(): void
    {
        $this->options = null;
    }

    /**
     * @return array<string, string|null> [subject_type => label|null]
     */
    protected function types(): array
    {
        if ($resolver = ActivitylogUi::subjectTypesResolver()) {
            return $this->normalize(call_user_func($resolver));
        }

        if ($only = (array) config('activitylog-ui.subject_types.only', [])) {
            return $this->normalize($only);
        }

        return $this->normalize($this->loggedTypes());
    }

    /**
     * Subject types present in the activity log table, ignoring any override.
     *
     * @return array<int, string>
     */
    public function loggedTypes(): array
    {
        $ttl = (int) config('activitylog-ui.subject_types.cache_ttl', 600);

        return $ttl > 0
            ? Cache::remember('activitylog-ui.subject_types', $ttl, fn () => $this->distinctTypes())
            : $this->distinctTypes();
    }

    protected function distinctTypes(): array
    {
        return $this->table->query()
            ->toBase()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->all();
    }

    protected function normalize(mixed $types): array
    {
        $types = $types instanceof \Illuminate\Support\Enumerable ? $types->all() : (array) $types;
        $normalized = [];

        foreach ($types as $key => $value) {
            if (is_int($key)) {
                $normalized[(string) $value] = null;
            } else {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * Label from ActivitylogUi::subjectTypes() or subject_types.only, without
     * scanning the activity log.
     */
    protected function customLabel(string $type): ?string
    {
        if (! ActivitylogUi::subjectTypesResolver() && (array) config('activitylog-ui.subject_types.only', []) === []) {
            return null;
        }

        $types = $this->types();
        $class = $this->className($type);
        $label = $types[$type] ?? $types[$class] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }
}
