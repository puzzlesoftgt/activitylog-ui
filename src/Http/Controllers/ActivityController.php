<?php

namespace Puzzlesoft\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Puzzlesoft\ActivitylogUi\Filters\ActivityFilter;
use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Support\ChangesResolver;
use Puzzlesoft\ActivitylogUi\Support\SubjectTypeResolver;
use Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver;

class ActivityController
{
    public function __construct(
        protected ActivityTable $table,
        protected UserOptionsResolver $users,
        protected SubjectTypeResolver $subjectTypes,
    ) {}

    public function index(Request $request, ActivityFilter $filter)
    {
        $filters = $filter->fromRequest($request);
        $model = $this->table->model();

        $query = $filter->apply($this->table->query(), $filters)
            ->orderByDesc($model->getKeyName());

        $perPageOptions = array_map('intval', (array) config('activitylog-ui.pagination.per_page_options', [25, 50, 100]));
        $perPage = (int) $request->query('per_page', config('activitylog-ui.pagination.per_page', 25));
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : (int) config('activitylog-ui.pagination.per_page', 25);

        $activities = config('activitylog-ui.pagination.simple', false)
            ? $query->simplePaginate($perPage)
            : $query->paginate($perPage);

        $activities->withQueryString();
        $userOptions = $this->users->options();
        $this->users->preload($activities);

        return view('activitylog-ui::index', [
            'activities' => $activities,
            'filters' => $filters,
            'activeFilters' => $filter->activeCount($filters),
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'userOptions' => $userOptions,
            'subjectTypeOptions' => $this->subjectTypes->options(),
            'logNames' => $this->logNames(),
            'events' => (array) config('activitylog-ui.events', []),
            'datePresets' => ActivityFilter::DATE_PRESETS,
            'users' => $this->users,
            'subjectTypes' => $this->subjectTypes,
            'historyUrl' => $filters['subject_type'] !== '' && $filters['subject_id'] !== '' && config('activitylog-ui.relations.enabled', true)
                ? route('activitylog-ui.history', ['subject_type' => $filters['subject_type'], 'subject_id' => $filters['subject_id']])
                : null,
        ]);
    }

    public function show(Request $request, string $activity, ChangesResolver $changes)
    {
        $activity = $this->table->query()->findOrFail($activity);
        $resolved = $changes->resolve($activity);
        $query = $request->query();
        $fromHistory = ($query['back'] ?? null) === 'history';
        unset($query['back']);
        $subjectType = $activity->getAttribute('subject_type');
        $subjectId = $activity->getAttribute('subject_id');
        $batch = $activity->getAttribute('batch_uuid');
        $batchMates = ($batch && $this->table->hasColumn('batch_uuid'))
            ? $this->table->query()
                ->where('batch_uuid', $batch)
                ->whereKeyNot($activity->getKey())
                ->orderByDesc($this->table->model()->getKeyName())
                ->limit(20)
                ->get()
            : collect();

        return view('activitylog-ui::show', [
            'activity' => $activity,
            'changes' => $changes->rows($activity),
            'extra' => $changes->mask($resolved['extra']),
            'raw' => array_map(
                fn (array $value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $changes->raw($activity)
            ),
            'backUrl' => route($fromHistory ? 'activitylog-ui.history' : 'activitylog-ui.index', $query),
            'subjectHistoryUrl' => $subjectType
                ? route('activitylog-ui.index', array_filter([
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                ], fn ($value) => $value !== null && $value !== ''))
                : null,
            'relationsHistoryUrl' => $subjectType && $subjectId !== null && $subjectId !== '' && config('activitylog-ui.relations.enabled', true)
                ? route('activitylog-ui.history', ['subject_type' => $subjectType, 'subject_id' => $subjectId])
                : null,
            'causerLabel' => $this->users->label($activity->getAttribute('causer_type'), $activity->getAttribute('causer_id')),
            'subjectTypes' => $this->subjectTypes,
            'changesResolver' => $changes,
            'batchMates' => $batchMates,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function logNames(): array
    {
        $ttl = (int) config('activitylog-ui.subject_types.cache_ttl', 600);

        $resolve = fn () => $this->table->query()
            ->toBase()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->all();

        return $ttl > 0 ? Cache::remember('activitylog-ui.log_names', $ttl, $resolve) : $resolve();
    }
}
