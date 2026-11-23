<?php

namespace Puzzlesoft\ActivitylogUi\Support;

use Illuminate\Support\Carbon;
use Throwable;

class ValueFormatter
{
    public const TYPE_NULL = 'null';

    public const TYPE_BOOL = 'bool';

    public const TYPE_NUMBER = 'number';

    public const TYPE_DATE = 'date';

    public const TYPE_JSON = 'json';

    public const TYPE_STRING = 'string';

    public const TYPE_EMPTY = 'empty';

    /**
     * @return array{type: string, display: string, raw: string|null}
     */
    public static function format(mixed $value): array
    {
        if ($value === null) {
            return ['type' => self::TYPE_NULL, 'display' => 'null', 'raw' => null];
        }

        if (is_bool($value)) {
            return [
                'type' => self::TYPE_BOOL,
                'display' => __($value ? 'activitylog-ui::ui.yes' : 'activitylog-ui::ui.no'),
                'raw' => $value ? 'true' : 'false',
            ];
        }

        if (is_int($value) || is_float($value)) {
            return ['type' => self::TYPE_NUMBER, 'display' => (string) $value, 'raw' => null];
        }

        if (is_array($value) || is_object($value)) {
            return [
                'type' => self::TYPE_JSON,
                'display' => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'raw' => null,
            ];
        }

        $value = (string) $value;

        if ($value === '') {
            return ['type' => self::TYPE_EMPTY, 'display' => '""', 'raw' => null];
        }

        if ($date = static::date($value)) {
            return ['type' => self::TYPE_DATE, 'display' => $date, 'raw' => $value];
        }

        return ['type' => self::TYPE_STRING, 'display' => $value, 'raw' => null];
    }

    /**
     * Formats ISO 8601 timestamps (as serialized by Eloquent) in the app timezone.
     */
    protected static function date(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->setTimezone(config('app.timezone', 'UTC'))
                ->format(config('activitylog-ui.date_format', 'd/m/Y H:i:s'));
        } catch (Throwable) {
            return null;
        }
    }
}
