<?php

declare(strict_types=1);

namespace Tests;

use App\Tips\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function numbers(): array
    {
        return [
            'local 07' => ['0712345678', '254712345678'],
            'local 01' => ['0110345678', '254110345678'],
            'spaces' => ['0712 345 678', '254712345678'],
            'plus 254' => ['+254 712-345-678', '254712345678'],
            '254 prefix' => ['254712345678', '254712345678'],
            'no leading zero' => ['712345678', '254712345678'],
            'too short' => ['071234567', null],
            'landline' => ['0201234567', null],
            'letters' => ['07123abc78', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('numbers')]
    public function testNormalize(string $input, ?string $expected): void
    {
        self::assertSame($expected, PhoneNumber::normalize($input));
    }

    public function testLast3(): void
    {
        self::assertSame('678', PhoneNumber::last3('254712345678'));
    }
}
