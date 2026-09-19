<?php

namespace Akika\LaravelExporter\Tests\Unit;

use Akika\LaravelExporter\Support\Canonicalizer;
use Akika\LaravelExporter\Tests\TestCase;

class CanonicalizerTest extends TestCase
{
    public function test_it_sorts_associative_array_keys(): void
    {
        $result = Canonicalizer::canonicalize([
            'store_id' => 12,
            'from' => '2026-01-01',
        ]);

        $this->assertSame(
            [
                'from' => '2026-01-01',
                'store_id' => 12,
            ],
            $result
        );
    }

    public function test_it_preserves_list_order(): void
    {
        $result = Canonicalizer::canonicalize([
            'columns' => [
                'name',
                'email',
                'phone',
            ],
        ]);

        $this->assertSame(
            [
                'columns' => [
                    'name',
                    'email',
                    'phone',
                ],
            ],
            $result
        );
    }

    public function test_it_canonicalizes_nested_arrays(): void
    {
        $result = Canonicalizer::canonicalize([
            'filters' => [
                'store_id' => 12,
                'status' => 'active',
            ],
        ]);

        $this->assertSame(
            [
                'filters' => [
                    'status' => 'active',
                    'store_id' => 12,
                ],
            ],
            $result
        );
    }
}
