<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Actividad;
use Workbench\App\Models\ActividadEstudiante;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect(['Krystel Monroy', 'Alanis Pineda', 'José Martínez', 'Alejandro Muralles'])
            ->map(fn ($name) => User::create(['username' => $name]));

        $subjects = ['App\\Models\\PolizaRequerimiento', 'App\\Models\\Pago', 'App\\Models\\Cliente', 'App\\Models\\Planilla'];
        $rows = [];

        for ($i = 0; $i < 180; $i++) {
            $event = ['created', 'updated', 'updated', 'updated', 'deleted'][$i % 5];
            $date = Carbon::now()->subMinutes($i * 97);
            $causer = $i % 7 === 0 ? null : $users[$i % $users->count()];

            $changes = match ($event) {
                'created' => ['attributes' => ['estado' => 'N', 'monto' => 800 + $i, 'cliente' => 'Cliente '.$i, 'activo' => true, 'fecha_registro' => $date->toIso8601ZuluString('microsecond')]],
                'deleted' => ['old' => ['estado' => 'C', 'monto' => 120, 'cliente' => 'Cliente '.$i, 'observaciones' => null]],
                default => [
                    'old' => ['estado' => 'N', 'fecha_pago' => null, 'planilla_id' => null, 'pago_pendiente' => 800, 'metadata' => ['canal' => 'web']],
                    'attributes' => ['estado' => 'C', 'fecha_pago' => '2026-11-21T06:00:00.000000Z', 'planilla_id' => 11928 + $i, 'pago_pendiente' => 0, 'metadata' => ['canal' => 'api', 'version' => 2]],
                ],
            };

            $rows[] = [
                'log_name' => $i % 9 === 0 ? 'billing' : 'default',
                'description' => $event,
                'subject_type' => $subjects[$i % count($subjects)],
                'subject_id' => 92000 + ($i % 40),
                'event' => $event,
                'causer_type' => $causer ? User::class : null,
                'causer_id' => $causer?->id,
                'attribute_changes' => json_encode($changes),
                'properties' => $i % 4 === 0 ? json_encode(['ip' => '10.0.0.'.$i, 'user_agent' => 'Mozilla/5.0']) : null,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        DB::table('activity_log')->insert(array_reverse($rows));

        $this->seedActividades($users->all());
    }

    /**
     * An Actividad with students, two of them deleted, to try the history page.
     */
    protected function seedActividades(array $users): void
    {
        $start = Carbon::now()->subHours(6);
        $rows = [];
        $log = function (string $type, int $id, string $event, array $changes, Carbon $date, ?User $causer) use (&$rows) {
            $rows[] = [
                'log_name' => 'default',
                'description' => $event,
                'subject_type' => $type,
                'subject_id' => $id,
                'event' => $event,
                'causer_type' => $causer ? User::class : null,
                'causer_id' => $causer?->id,
                'attribute_changes' => json_encode($changes),
                'properties' => null,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        };

        $actividad = Actividad::create(['titulo' => 'Tarea de matemáticas']);
        $log(Actividad::class, $actividad->id, 'created', ['attributes' => ['titulo' => 'Tarea de matemáticas']], $start, $users[3]);

        foreach (range(1, 6) as $n) {
            $attributes = ['actividad_id' => $actividad->id, 'estudiante_id' => 1000 + $n, 'nota' => null, 'estado' => 'N'];
            $child = ActividadEstudiante::create($attributes);
            $log(ActividadEstudiante::class, $child->id, 'created', ['attributes' => $attributes], $start->copy()->addMinutes($n), $users[3]);

            if ($n % 2 === 0) {
                $child->update(['nota' => 70 + $n, 'estado' => 'C']);
                $log(ActividadEstudiante::class, $child->id, 'updated', [
                    'old' => ['nota' => null, 'estado' => 'N'],
                    'attributes' => ['nota' => 70 + $n, 'estado' => 'C'],
                ], $start->copy()->addHours(2)->addMinutes($n), $users[$n % 3]);
            }

            if ($n >= 5) {
                $log(ActividadEstudiante::class, $child->id, 'deleted', [
                    'old' => $child->only(['actividad_id', 'estudiante_id', 'nota', 'estado']),
                ], $start->copy()->addHours(4)->addMinutes($n), $users[1]);
                $child->delete();
            }
        }

        $log(Actividad::class, $actividad->id, 'updated', [
            'old' => ['titulo' => 'Tarea de matemáticas'],
            'attributes' => ['titulo' => 'Tarea de matemáticas (unidad 2)'],
        ], $start->copy()->addHours(5), $users[3]);
        $actividad->update(['titulo' => 'Tarea de matemáticas (unidad 2)']);

        DB::table('activity_log')->insert(collect($rows)->sortBy('created_at')->values()->all());
    }
}
