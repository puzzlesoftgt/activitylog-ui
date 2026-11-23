<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Puzzlesoft\ActivitylogUi\ActivitylogUi;
use Workbench\App\Models\Actividad;
use Workbench\App\Models\ActividadEstudiante;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ActivitylogUi::relations(Actividad::class, [
            ActividadEstudiante::class,
        ]);
    }
}
