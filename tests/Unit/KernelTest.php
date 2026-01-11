<?php

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    public function testKernelBoots(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $this->assertSame('test', $kernel->getEnvironment());

        $kernel->shutdown();
    }
}
