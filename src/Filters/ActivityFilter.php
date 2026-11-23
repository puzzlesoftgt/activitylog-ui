<?php

namespace Puzzlesoft\ActivitylogUi\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Support\JsonSql;
use Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver;
use Throwable;

class ActivityFilter
{
    public const DATE_PRESETS = ['all', 'today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'custom'];

    public function __construct(
        protected ActivityTable $table,
        protected UserOptionsResolver $users,
    ) {}

    /**
     * Normalized filters from the query string.
     *
     * @return array{search: string, causer: string, subject_type: string, subject_id: string, events: array, log_name: string, date: string, from: string, to: string, batch: string}
     */
    public function fromRequest(Request $request): array
    {
        $events = array_values(array_intersect(
            (array) $request->query('events', []),
            (array) config('activitylog-ui.events', [])
        ));

        $date = (string) $request->query('date', 'all');

        return [
            'search' => trim((string) $request->query('search', '')),
            'causer' => trim((string) $request->query('causer', '')),
            'subject_type' => trim((string) $request->query('subject_type', '')),
            'subject_id' => trim((string) $request->query('subject_id', '')),
            'events' => $events,
            'log_name' => trim((string) $request->query('log_name', '')),
            'date' => in_array($date, self::DATE_PRESETS, true) ? $date : 'all',
            'from' => $this->validDate($request->query('from')),
            'to' => $this->validDate($request->query('to')),
            'batch' => $this->validBatch($request->query('batch')),
        ];
    }

    public function apply(Builder $query, array $filters): Builder
    {
        if (($filters['search'] ?? '') !== '') {
            $this->applySearch($query, $filters['search']);
        }

        if (($filters['causer'] ?? '') !== '') {
            $this->applyCauser($query, $filters['causer']);
        }

        if (($filters['subject_type'] ?? '') !== '') {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (($filters['subject_id'] ?? '') !== '') {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['events'])) {
            $query->whereIn('event', $filters['events']);
        }

        if (($filters['log_name'] ?? '') !== '') {
            $query->where('log_name', $filters['log_name']);
        }

        if (($filters['batch'] ?? '') !== '' && $this->table->hasColumn('batch_uuid')) {
            $query->where('batch_uuid', $filters['batch']);
        }

        if ($range = $this->dateRange($filters['date'] ?? 'all', $filters['from'] ?? '', $filters['to'] ?? '')) {
            [$from, $to] = $range;

            if ($from) {
                $query->where('created_at', '>=', $from);
            }

            if ($to) {
                $query->where('created_at', '<=', $to);
            }
        }

        return $query;
    }

    /**
     * Number of active filters, used for the "clear" button badge.
     */
    public function activeCount(array $filters): int
    {
        return count(array_filter([
            $filters['search'] !== '',
            $filters['causer'] !== '',
            $filters['subject_type'] !== '',
            $filters['subject_id'] !== '',
            $filters['events'] !== [],
            $filters['log_name'] !== '',
            $filters['batch'] !== '',
            $filters['date'] !== 'all',
        ]));
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}|null
     */
    public function dateRange(string $preset, string $from = '', string $to = ''): ?array
    {
        $now = Carbon::now(config('app.timezone', 'UTC'));

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'custom' => ($from === '' && $to === '') ? null : [
                $from !== '' ? Carbon::parse($from, $now->getTimezone())->startOfDay() : null,
                $to !== '' ? Carbon::parse($to, $now->getTimezone())->endOfDay() : null,
            ],
            default => null,
        };
    }

    protected function applyCauser(Builder $query, string $causer): void
    {
        if ($causer === UserOptionsResolver::SYSTEM) {
            $query->whereNull('causer_id');

            return;
        }

        $query->where('causer_id', $causer);

        $types = $this->users->causerTypes();

        if ($types !== []) {
            $query->whereIn('causer_type', $types);
        }
    }

    protected function applySearch(Builder $query, string $term): void
    {
        $columns = array_values(array_filter(
            (array) config('activitylog-ui.search.columns', []),
            fn ($column) => $this->table->hasColumn($column)
        ));

        if ($columns === []) {
            return;
        }

        $terms = array_values(array_unique([$term, $this->jsonEscaped($term)]));
        $grammar = $query->getQuery()->getGrammar();
        $driver = $this->table->driver();

        $query->where(function (Builder $where) use ($columns, $terms, $grammar, $driver) {
            foreach ($columns as $column) {
                $expression = JsonSql::like($grammar->wrap($column), $driver);

                foreach ($terms as $term) {
                    $where->orWhereRaw($expression, [JsonSql::likeValue($term, $driver)]);
                }
            }
        });
    }

    /**
     * The term as it would appear inside a JSON document encoded with the
     * default json_encode() flags (escaped unicode and slashes).
     */
    protected function jsonEscaped(string $term): string
    {
        $encoded = json_encode($term);

        return $encoded === false ? $term : substr($encoded, 1, -1);
    }

    protected function validDate(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        try {
            Carbon::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return '';
        }

        return $value;
    }

    protected function validBatch(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^[A-Za-z0-9-]{1,64}$/', $value) ? $value : '';
    }
}
