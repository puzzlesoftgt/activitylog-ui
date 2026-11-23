# Activitylog UI

A modern, friendly interface to browse the activity logs recorded with [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog). Inspired by [muhammadsadeeq/laravel-activitylog-ui](https://github.com/muhammadsadeeq/laravel-activitylog-ui).

_Una interfaz moderna para visualizar los logs registrados con spatie/laravel-activitylog. La documentación en español está más abajo._

- Filters by user (customizable query, no `name`/`email` assumptions), subject type (auto-detected), subject ID, event, log name and date range.
- Free text search in the description and in the logged JSON (`properties` in v4, `attribute_changes` in v5).
- Detail page with a **before / after table** for updates, and the created / deleted values for the other events.
- Light, dark and system themes. Ships precompiled Tailwind + Alpine assets; nothing to build in your app.
- English and Spanish translations.

## Requirements

| Package                    | Versions                        |
| -------------------------- | ------------------------------- |
| PHP                        | 8.1+ (8.4+ with activitylog v5) |
| Laravel                    | 10, 11, 12, 13                  |
| spatie/laravel-activitylog | 4.x, 5.x                        |

## Installation

```bash
composer require puzzlesoft/activitylog-ui
php artisan activitylog-ui:install
```

Open `/activitylog`. The install command publishes the config file and the assets. Assets are also tagged as `laravel-assets`, so the default `post-update-cmd` of Laravel keeps them updated. If the assets are not published, they are served by the package itself.

## Authorization

In the `local` environment anyone can access the UI. In any other environment, access is denied unless you register a callback in `AppServiceProvider::boot()`:

```php
use Puzzlesoft\ActivitylogUi\ActivitylogUi;

public function boot(): void
{
    ActivitylogUi::auth(function ($request) {
        return $request->user()?->hasRole('admin');
    });
}
```

When a callback is registered, it is used in every environment.

## "Back to app" link

Set a named route (`back_route`) or URL (`back_url`) in `config/activitylog-ui.php`, or `null` to hide the link. For dynamic destinations per user or role, register a callback in `AppServiceProvider::boot()` (it may return a route name, path, full URL or `null`):

```php
ActivitylogUi::backUrl(function ($request) {
    return $request->user()?->is_admin ? 'admin.dashboard' : 'home';
});
```

## User filter

By default the options are built from `auth.providers.users.model`. `users.label_column` is the ordered list of columns tried for each label (`name`, `nombre`, `username`, `email`). The first non-empty value wins, and a column that is not on the table is skipped. If none of them exist, the primary key is shown. Set the list to the columns you actually want; nothing else is tried.

You can provide your own query. It must return `[id => label]` (array or collection), or a collection of user models:

```php
ActivitylogUi::users(function () {
    return User::orderBy('username')->pluck('username', 'id')->toArray();
});
```

The same list is used to display the user of each activity. Activities without causer are shown as **System** and can be filtered with the "System" option.

## Subject type filter

The subject types are detected with `SELECT DISTINCT subject_type` on the activity log table, so it works with the `LogsActivity` trait and with manual `activity()->performedOn()` calls. Morph map aliases are resolved. The result is cached (`subject_types.cache_ttl`, 10 minutes by default).

On a large log that `DISTINCT` walks every logged type. Skip it with `subject_types.only` or `ActivitylogUi::subjectTypes()`.

Names come from one place. `ActivitylogUi::label()` sets the name of any model (the history title of a parent uses it). A relation `label` is registered the same way and is used in the subject filter, the subject column and the history selector. `ActivitylogUi::subjectTypes()` labels win when both exist.

You can also provide a fixed list:

```php
// config/activitylog-ui.php
'subject_types' => [
    'only' => [App\Models\Invoice::class, App\Models\Customer::class],
    'except' => [App\Models\Session::class],
],

// or with a callback, optionally with labels.
// Inside App\Providers, prefix the class with \ (or import it).
// Without it, PHP resolves App\Models\Invoice as App\Providers\App\Models\Invoice.
ActivitylogUi::subjectTypes(fn () => [
    \App\Models\Invoice::class => 'Invoices',
    \App\Models\Customer::class => 'Customers',
]);

ActivitylogUi::label(\App\Models\Poliza::class, 'Pólizas');
```

## Search

The search box looks for the text in `description`, `properties` and `attribute_changes` (only the columns that exist in your table; configurable with `search.columns`). JSON columns are cast to text and matched with `LIKE` (MySQL/MariaDB, PostgreSQL, SQLite and SQL Server are supported).

On very large tables this is a full scan: combine it with a date range, subject type or user. To filter a specific record, use **Subject type + Subject ID** instead, which uses the index created by spatie.

The list marks activities that share a `batch_uuid` and links them. The detail page lists the other activities of that batch. Filter the index with `?batch=` to see the whole batch.

## Record history with relations

The history page opens on the record itself. Relations are not detected on their own: register the ones you want, and the page offers them in a selector. Choosing one loads only that relation.

```php
ActivitylogUi::relations(\App\Models\Poliza::class, [
    \App\Models\PolizaCobertura::class => ['label' => 'Coberturas'],
    \App\Models\PolizaDocumento::class => ['foreign_key' => 'poliza_id', 'label' => 'Documentos'],
    \App\Models\PolizaGuia::class,           // conventional foreign key, class name as label
]);
```

The selector and the subject column use `label` when you set one. Without it, the short class name is shown. A string value is still the foreign key, and `false` excludes a discovered relation.

Open it from the detail page ("History with relations") or from the list when filtering by subject type + ID:

```
/activitylog/history?subject_type=App\Models\Poliza&subject_id=24350&relation=App\Models\PolizaCobertura
```

**How the children of the chosen relation are found**

- _Living children_: `SELECT id FROM child_table WHERE foreign_key = parent_id`. This includes children whose logs never stored the key (an `updated` with `logOnlyDirty()` does not).
- _Deleted children_: their rows are gone, so their IDs are read from logs of that subject type whose `attributes` or `old` contain `foreign_key = parent_id`. Those IDs are marked as deleted.

The list then shows every activity of the combined IDs, including `updated` logs that do not contain the foreign key. For deleted children to appear, the key must be logged on create or delete (`logAll()` or included in `logOnly()`).

`relations.auto_discover` can also offer every logged model whose table has the parent's foreign key. It stays off by default. `relations.except` hides types. Only one level is queried, and only the relation selected on the page.

**Performance**: nothing is read from child tables until a relation is selected. The historical lookup filters by `subject_type`, the first column of the spatie `subject` index, and then scans the JSON of those rows.

## Performance tips

- Set `pagination.simple` to `true` to avoid the `COUNT(*)` query on huge tables.
- Recommended indexes besides the ones created by spatie: `created_at`, `event`.

## Configuration

```bash
php artisan vendor:publish --tag=activitylog-ui-config
```

| Key                                                                                       | Description                                                                                            |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `enabled`                                                                                 | Register the routes (`ACTIVITYLOG_UI_ENABLED`).                                                        |
| `route.prefix`, `route.domain`, `route.middleware`                                        | Route group options. The authorization middleware is always added.                                     |
| `title`, `back_route`, `back_url`                                                         | Header title and "Back to app" route name or URL (`null` hides it; or use `ActivitylogUi::backUrl()`). |
| `users.model`, `users.label_column`, `users.causer_types`, `users.cache_ttl`              | Default user filter query and causer morph classes.                                                    |
| `subject_types.only`, `subject_types.except`, `subject_types.cache_ttl`                   | Subject type filter.                                                                                   |
| `events`                                                                                  | Event checkboxes. Includes `restored` for soft deletes.                                                |
| `pagination.per_page`, `pagination.per_page_options`, `pagination.simple`                 | Pagination.                                                                                            |
| `search.columns`                                                                          | Columns used by the search.                                                                            |
| `relations.enabled`, `relations.auto_discover`, `relations.except`, `relations.cache_ttl` | Record history with relations.                                                                         |
| `hidden_attributes`                                                                       | Attributes masked in the detail page (`password`, `remember_token`).                                   |
| `date_format`                                                                             | Date format used in the UI.                                                                            |

Views and translations can be published with the `activitylog-ui-views` and `activitylog-ui-lang` tags.

## Development

```bash
composer install
npm install
npm run build        # compiles resources/dist
composer test        # Pest + Orchestra Testbench
composer serve       # demo app with seeded activities at http://127.0.0.1:8000/activitylog
```

---

## Español

### Instalación

```bash
composer require puzzlesoft/activitylog-ui
php artisan activitylog-ui:install
```

La interfaz queda disponible en `/activitylog`.

### Autorización y enlace "Volver a la aplicación"

En el entorno `local` cualquiera puede acceder. En cualquier otro entorno el acceso se niega, salvo que registres un callback en `AppServiceProvider`:

```php
use Puzzlesoft\ActivitylogUi\ActivitylogUi;

ActivitylogUi::auth(fn ($request) => $request->user()?->is_admin);
```

Para configurar a dónde regresa el botón **Volver a la aplicación** puedes definir `back_route` (nombre de ruta, p. ej. `'admin.dashboard'`) o `back_url` en `config/activitylog-ui.php`, o registrar una función en `AppServiceProvider` según el usuario o su rol (puede devolver un nombre de ruta, ruta relativa, URL completa o `null` para ocultarlo):

```php
ActivitylogUi::backUrl(fn ($request) => $request->user()?->is_admin ? 'admin.dashboard' : 'home');
```

### Filtro de usuarios

Por defecto se usa el modelo de `auth.providers.users.model`. `users.label_column` es la lista ordenada de columnas que se prueban para la etiqueta (`name`, `nombre`, `username`, `email`). Gana el primer valor no vacío y se omite la columna que no exista en la tabla. Si ninguna existe, se muestra la llave primaria. La lista es la única fuente: no se prueba ninguna columna que no esté ahí. Puedes definir tu propia consulta, que debe devolver `[id => etiqueta]`:

```php
ActivitylogUi::users(fn () => User::orderBy('username')->pluck('username', 'id')->toArray());
```

### Filtro de tipo de registro (subject type)

Se detecta automáticamente con un `SELECT DISTINCT subject_type`. En un log grande ese recorrido se evita con `subject_types.only` o con `ActivitylogUi::subjectTypes()`.

Los nombres salen de un solo lugar. `ActivitylogUi::label(\App\Models\Poliza::class, 'Pólizas')` es el título del historial y el texto del filtro. La `label` de una relación se registra igual y se usa en el filtro, en la columna Registro y en el selector. Si además hay una etiqueta en `subjectTypes()`, esa gana.

En `AppServiceProvider` la clase lleva `\` al inicio, o un `use`. Sin eso, PHP la resuelve como `App\Providers\App\Models\Poliza`:

```php
ActivitylogUi::subjectTypes(fn () => [
    \App\Models\Poliza::class => 'Pólizas',
    \App\Models\PolizaRequerimiento::class => 'Requerimientos',
    \App\Models\PolizaDocumento::class => 'Documentos de Póliza',
]);
```

### Búsqueda

Busca en `description` y en el JSON guardado (`properties` en v4, `attribute_changes` en v5). Para encontrar los logs de un registro concreto, lo más rápido es usar **Tipo de registro + ID**.

### Detalle

Cada actividad tiene su propia página. En los eventos `updated` se muestra una tabla con el campo, el valor anterior y el valor nuevo; en `created` los datos con los que se creó y en `deleted` los datos que tenía al eliminarse. Las propiedades adicionales y el JSON original están disponibles en secciones aparte.

### Historial con relaciones

El historial abre en el registro mismo. Las relaciones no se detectan solas: se registran y la pantalla las ofrece en un selector. Al elegir una, solo se cargan sus logs.

```php
ActivitylogUi::relations(\App\Models\Poliza::class, [
    \App\Models\PolizaCobertura::class => ['label' => 'Coberturas'],
    \App\Models\PolizaDocumento::class => ['foreign_key' => 'poliza_id', 'label' => 'Documentos'],
    \App\Models\PolizaGuia::class,           // llave por convención, etiqueta = nombre de la clase
]);
```

El selector y la columna Registro usan `label`. Si no lo pones, se muestra el nombre corto de la clase. Un string sigue siendo la llave foránea y `false` excluye una relación detectada.

Se abre desde el detalle ("Historial con relaciones") o desde el listado filtrado por tipo + ID.

Los hijos que todavía existen se leen de su tabla (`WHERE poliza_id = 24350`), así entran también los `updated` que no guardaron la llave (`logOnlyDirty()`). Los eliminados ya no están en la tabla: sus IDs salen de los logs cuyo `attributes` u `old` tiene esa llave, y se marcan como eliminados. Por eso el `created` o el `deleted` debe guardar la llave (`logAll()` o incluida en `logOnly()`). Con la unión de esos IDs se listan todos sus movimientos.

`relations.auto_discover` puede ofrecer además todo modelo logueado cuya tabla tenga la llave del padre; queda apagado. `relations.except` oculta tipos. Solo se consulta un nivel y solo la relación elegida. Hasta que no se elige, no se lee ninguna tabla hija.

## License

MIT
