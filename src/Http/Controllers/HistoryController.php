<?php

namespace Puzzlesoft\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Puzzlesoft\ActivitylogUi\Filters\ActivityFilter;
use Puzzlesoft\ActivitylogUi\Relations\RelationResolver;
use Puzzlesoft\ActivitylogUi\Support\ActivityTable;
use Puzzlesoft\ActivitylogUi\Support\SubjectTypeResolver;
use Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver;

class HistoryController
{
    public function __invoke(
        Request $request,
        ActivityTable $table,
        ActivityFilter $filter,
        RelationResolver $relations,
        SubjectTypeResolver $subjectTypes,
        UserOptionsResolver $users,
    ) {
        $type = trim((string) $request->query('subject_type', ''));
        $id = trim((string) $request->query('subject_id', ''));

        abort_if($type === '' || $id === '', 404);

        $definitions = $relations->definitions($type);
        $relation = (string) $request->query('relation', RelationResolver::SELF);
        $relation = $relation === RelationResolver::SELF || isset($definitions[$relation])
            ? $relation
            : RelationResolver::SELF;

        $filters = array_merge($filter->fromRequest($request), [
            'search' => '',
            'causer' => '',
            'subject_type' => '',
            'subject_id' => '',
            'log_name' => '',
            'batch' => '',
        ]);

        $query = $filter->apply($relations->query($type, $id, $relation), $filters)
            ->orderByDesc($table->model()->getKeyName());

        $perPageOptions = array_map('intval', (array) config('activitylog-ui.pagination.per_page_options', [25, 50, 100]));
        $perPage = (int) $request->query('per_page', config('activitylog-ui.pagination.per_page', 25));
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : (int) config('activitylog-ui.pagination.per_page', 25);

        $activities = config('activitylog-ui.pagination.simple', false)
            ? $query->simplePaginate($perPage)
            : $query->paginate($perPage);

        $activities->withQueryString();
        $users->preload($activities);

        $deleted = [];
        if ($relation !== RelationResolver::SELF) {
            $deleted[$relation] = array_flip($relations->childIds($definitions[$relation], $id)['deleted']);
        }

        return view('activitylog-ui::history', [
            'subjectType' => $type,
            'subjectId' => $id,
            'relations' => $definitions,
            'relation' => $relation,
            'deleted' => $deleted,
            'activities' => $activities,
            'filters' => $filters,
            'events' => (array) config('activitylog-ui.events', []),
            'datePresets' => ActivityFilter::DATE_PRESETS,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'users' => $users,
            'subjectTypes' => $subjectTypes,
        ]);
    }
}
