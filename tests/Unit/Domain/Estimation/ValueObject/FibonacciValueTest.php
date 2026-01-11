<?php

namespace App\Tests\Unit\Domain\Estimation\ValueObject;

use App\Domain\Estimation\ValueObject\FibonacciValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FibonacciValueTest extends TestCase
{
    #[DataProvider('validValuesProvider')]
    public function testFromStringWithValidValues(string $value): void
    {
        $fibonacciValue = FibonacciValue::fromString($value);

        $this->assertSame($value, $fibonacciValue->toString());
    }

    public static function validValuesProvider(): array
    {
        return [
            ['0'],
            ['1'],
            ['2'],
            ['3'],
            ['5'],
            ['8'],
            ['13'],
            ['21'],
            ['?'],
        ];
    }

    public function testFromStringWithInvalidValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Fibonacci value "4"');

        FibonacciValue::fromString('4');
    }

    public function testFromStringWithInvalidNumericValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FibonacciValue::fromString('100');
    }

    public function testFromStringWithEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FibonacciValue::fromString('');
    }

    #[DataProvider('validValuesProvider')]
    public function testIsValidWithValidValues(string $value): void
    {
        $this->assertTrue(FibonacciValue::isValid($value));
    }

    public function testIsValidWithInvalidValues(): void
    {
        $this->assertFalse(FibonacciValue::isValid('4'));
        $this->assertFalse(FibonacciValue::isValid('100'));
        $this->assertFalse(FibonacciValue::isValid(''));
        $this->assertFalse(FibonacciValue::isValid('-1'));
        $this->assertFalse(FibonacciValue::isValid('abc'));
    }

    public function testIsUncertainWithQuestionMark(): void
    {
        $value = FibonacciValue::fromString('?');

        $this->assertTrue($value->isUncertain());
    }

    #[DataProvider('numericValuesProvider')]
    public function testIsUncertainWithNumericValues(string $value): void
    {
        $fibonacciValue = FibonacciValue::fromString($value);

        $this->assertFalse($fibonacciValue->isUncertain());
    }

    public static function numericValuesProvider(): array
    {
        return [
            ['0'],
            ['1'],
            ['2'],
            ['3'],
            ['5'],
            ['8'],
            ['13'],
            ['21'],
        ];
    }

    #[DataProvider('numericValuesProvider')]
    public function testIsNumericWithNumericValues(string $value): void
    {
        $fibonacciValue = FibonacciValue::fromString($value);

        $this->assertTrue($fibonacciValue->isNumeric());
    }

    public function testIsNumericWithQuestionMark(): void
    {
        $value = FibonacciValue::fromString('?');

        $this->assertFalse($value->isNumeric());
    }

    #[DataProvider('numericToIntProvider')]
    public function testToIntWithNumericValues(string $value, int $expected): void
    {
        $fibonacciValue = FibonacciValue::fromString($value);

        $this->assertSame($expected, $fibonacciValue->toInt());
    }

    public static function numericToIntProvider(): array
    {
        return [
            ['0', 0],
            ['1', 1],
            ['2', 2],
            ['3', 3],
            ['5', 5],
            ['8', 8],
            ['13', 13],
            ['21', 21],
        ];
    }

    public function testToIntWithQuestionMarkReturnsNull(): void
    {
        $value = FibonacciValue::fromString('?');

        $this->assertNull($value->toInt());
    }

    public function testValue(): void
    {
        $value = FibonacciValue::fromString('5');

        $this->assertSame('5', $value->value());
    }

    public function testEquals(): void
    {
        $value1 = FibonacciValue::fromString('5');
        $value2 = FibonacciValue::fromString('5');
        $value3 = FibonacciValue::fromString('8');

        $this->assertTrue($value1->equals($value2));
        $this->assertFalse($value1->equals($value3));
    }

    public function testToString(): void
    {
        $value = FibonacciValue::fromString('13');

        $this->assertSame('13', (string) $value);
    }

    public function testAllValues(): void
    {
        $values = FibonacciValue::allValues();

        $this->assertCount(9, $values);
        $this->assertContains('0', $values);
        $this->assertContains('1', $values);
        $this->assertContains('2', $values);
        $this->assertContains('3', $values);
        $this->assertContains('5', $values);
        $this->assertContains('8', $values);
        $this->assertContains('13', $values);
        $this->assertContains('21', $values);
        $this->assertContains('?', $values);
    }
}
