<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Exception;

use App\Domain\Estimation\ValueObject\FibonacciValue;

final class InvalidFibonacciValueException extends \DomainException
{
    public static function withValue(string $value): self
    {
        return new self(sprintf(
            'Invalid Fibonacci value "%s". Valid values are: %s',
            $value,
            implode(', ', FibonacciValue::VALUES)
        ));
    }
}
