<?php

use Puzzlesoft\ActivitylogUi\Support\JsonSql;

it('builds json value expressions per driver', function (string $driver, string $expected) {
    expect(JsonSql::value('"properties"', ['attributes', 'actividad_id'], $driver))->toBe($expected);
})->with([
    'mysql' => ['mysql', "JSON_UNQUOTE(JSON_EXTRACT(\"properties\", '$.\"attributes\".\"actividad_id\"'))"],
    'mariadb' => ['mariadb', "JSON_UNQUOTE(JSON_EXTRACT(\"properties\", '$.\"attributes\".\"actividad_id\"'))"],
    'pgsql' => ['pgsql', "(CAST(\"properties\" AS jsonb) #>> '{attributes,actividad_id}')"],
    'sqlsrv' => ['sqlsrv', "JSON_VALUE(\"properties\", '$.attributes.actividad_id')"],
    'sqlite' => ['sqlite', "CAST(json_extract(\"properties\", '$.attributes.actividad_id') AS TEXT)"],
]);

it('rejects unsafe json path segments', function () {
    JsonSql::value('"properties"', ['attributes', "x') OR 1=1 --"], 'mysql');
})->throws(InvalidArgumentException::class);
