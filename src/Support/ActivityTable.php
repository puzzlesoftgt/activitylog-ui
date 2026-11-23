<?php

namespace Puzzlesoft\ActivitylogUi\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Resolves the activity model, its table and connection for both
 * spatie/laravel-activitylog v4 and v5.
 */
class ActivityTable
{
    protected ?array $columns = null;

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return config('activitylog.activity_model') ?: Activity::class;
    }

    public function model(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }

    public function query(): Builder
    {
        return $this->model()->newQuery();
    }

    public function hasColumn(string $column): bool
    {
        if ($this->columns === null) {
            $model = $this->model();

            $this->columns = array_map(
                'strtolower',
                $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable())
            );
        }

        return in_array(strtolower($column), $this->columns, true);
    }

    public function driver(): string
    {
        return $this->model()->getConnection()->getDriverName();
    }
}
