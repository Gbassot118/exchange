<?php

namespace App\Tests\Unit\Domain\Session\ValueObject;

use App\Domain\Session\ValueObject\InviteCode;
use PHPUnit\Framework\TestCase;

class InviteCodeTest extends TestCase
{
    public function testGenerateCreatesValidCode(): void
    {
        $code = InviteCode::generate();

        $this->assertSame(32, strlen($code->toString()));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $code->toString());
    }

    public function testGenerateCreatesUniqueCode(): void
    {
        $code1 = InviteCode::generate();
        $code2 = InviteCode::generate();

        $this->assertFalse($code1->equals($code2));
    }

    public function testFromStringWithValidCode(): void
    {
        $validCode = 'abcdef0123456789abcdef0123456789';
        $code = InviteCode::fromString($validCode);

        $this->assertSame($validCode, $code->toString());
    }

    public function testFromStringRejectsShortCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invite code must be exactly 32 characters');

        InviteCode::fromString('abc123');
    }

    public function testFromStringRejectsLongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invite code must be exactly 32 characters');

        InviteCode::fromString('abcdef0123456789abcdef0123456789extra');
    }

    public function testFromStringRejectsNonHexadecimal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invite code must be a valid hexadecimal string');

        InviteCode::fromString('zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz');
    }

    public function testEquals(): void
    {
        $code1 = InviteCode::fromString('abcdef0123456789abcdef0123456789');
        $code2 = InviteCode::fromString('abcdef0123456789abcdef0123456789');
        $code3 = InviteCode::fromString('123456789abcdef0123456789abcdef0');

        $this->assertTrue($code1->equals($code2));
        $this->assertFalse($code1->equals($code3));
    }

    public function testToString(): void
    {
        $value = 'abcdef0123456789abcdef0123456789';
        $code = InviteCode::fromString($value);

        $this->assertSame($value, (string) $code);
    }
}
