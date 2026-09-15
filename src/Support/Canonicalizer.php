<?php

namespace Akika\LaravelExporter\Support;

class Canonicalizer
{
    public static function canonicalize(
        mixed $value
    ): mixed {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn(mixed $item) =>
                self::canonicalize($item),
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize(
                $item
            );
        }

        return $value;
    }
}
