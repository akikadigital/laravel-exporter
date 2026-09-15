<?php

namespace Akika\LaravelExporter\Support;

use Akika\LaravelExporter\Enums\ExportFormat;
use Illuminate\Database\Eloquent\Model;
use JsonException;

class ExportFingerprint
{
    /**
     * @throws JsonException
     */
    public static function generate(
        string $exporter,
        ?Model $owner,
        ExportFormat $format,
        array $options,
    ): string {
        $payload = [
            'exporter' => $exporter,

            'owner_type' =>
            $owner?->getMorphClass(),

            'owner_id' =>
            $owner?->getKey(),

            'format' =>
            $format->value,

            'options' =>
            Canonicalizer::canonicalize(
                $options
            ),
        ];

        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
