<?php

namespace Puzzlesoft\ActivitylogUi\Support;

use InvalidArgumentException;

/**
 * Driver specific SQL for (JSON) columns of the activity log table.
 */
class JsonSql
{
    /**
     * SQL that casts a (possibly JSON) column to text and matches it with LIKE.
     * "!" is used as escape character because it behaves the same on every driver.
     */
    public static function like(string $wrappedColumn, string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => "LOWER(CAST({$wrappedColumn} AS CHAR)) LIKE ? ESCAPE '!'",
            'pgsql' => "CAST({$wrappedColumn} AS TEXT) ILIKE ? ESCAPE '!'",
            'sqlsrv' => "CAST({$wrappedColumn} AS NVARCHAR(MAX)) LIKE ? ESCAPE '!'",
            default => "{$wrappedColumn} LIKE ? ESCAPE '!'",
        };
    }

    public static function likeValue(string $term, string $driver): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $escaped = mb_strtolower($escaped);
        }

        return '%'.$escaped.'%';
    }

    /**
     * SQL returning the value at a JSON path as text, to be compared with a
     * string binding (e.g. "{expr} = ?").
     *
     * @param  array<int, string>  $path  e.g. ['attributes', 'actividad_id']
     */
    public static function value(string $wrappedColumn, array $path, string $driver): string
    {
        foreach ($path as $segment) {
            if (! static::isSafeKey($segment)) {
                throw new InvalidArgumentException("Invalid JSON path segment [{$segment}].");
            }
        }

        return match ($driver) {
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT({$wrappedColumn}, '$.\"".implode('"."', $path)."\"'))",
            'pgsql' => "(CAST({$wrappedColumn} AS jsonb) #>> '{".implode(',', $path)."}')",
            'sqlsrv' => "JSON_VALUE({$wrappedColumn}, '$.".implode('.', $path)."')",
            default => "CAST(json_extract({$wrappedColumn}, '$.".implode('.', $path)."') AS TEXT)",
        };
    }

    public static function isSafeKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $key);
    }
}
