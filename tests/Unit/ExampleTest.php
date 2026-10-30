<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_specification_is_preserved_byte_for_byte(): void
    {
        $this->assertSame(
            '7885ee818100b1ac35445e4f39bbcaed2be533f67db9bac9930e4700fc089688',
            hash_file('sha256', dirname(__DIR__, 2).'/docs/especificacao.md')
        );
    }
}
