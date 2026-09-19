<?php

namespace Akika\LaravelExporter\Enums;

enum ExportStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isActive(): bool
    {
        return in_array(
            $this,
            [
                self::PENDING,
                self::PROCESSING,
            ],
            true
        );
    }

    public function isFinished(): bool
    {
        return in_array(
            $this,
            [
                self::COMPLETED,
                self::FAILED,
                self::CANCELLED,
            ],
            true
        );
    }

    public static function active(): array
    {
        return [
            self::PENDING->value,
            self::PROCESSING->value,
        ];
    }

    public static function finished(): array
    {
        return [
            self::COMPLETED->value,
            self::FAILED->value,
            self::CANCELLED->value,
        ];
    }
}
