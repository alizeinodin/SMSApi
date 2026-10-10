<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Exceptions\InvalidPhoneNumberException;
use Alizeinodin\SmsApi\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('localProvider')]
    public function test_it_normalizes_to_local_format(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input, [
            'enabled' => true,
            'format' => PhoneNumber::FORMAT_LOCAL,
            'country_code' => '98',
            'strict' => true,
        ]));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function localProvider(): array
    {
        return [
            'already local' => ['09123456789', '09123456789'],
            'without zero' => ['9123456789', '09123456789'],
            'e164' => ['+989123456789', '09123456789'],
            'double zero' => ['00989123456789', '09123456789'],
            'country digits' => ['989123456789', '09123456789'],
            'spaces' => ['+98 912 345 6789', '09123456789'],
            'dashes' => ['0912-345-6789', '09123456789'],
        ];
    }

    public function test_it_formats_e164_and_digits(): void
    {
        $this->assertSame('+989123456789', PhoneNumber::normalize('09123456789', [
            'format' => PhoneNumber::FORMAT_E164,
            'strict' => true,
        ]));

        $this->assertSame('989123456789', PhoneNumber::normalize('09123456789', [
            'format' => PhoneNumber::FORMAT_DIGITS,
            'strict' => true,
        ]));
    }

    public function test_strict_mode_throws_on_invalid(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        PhoneNumber::normalize('02188776655', [
            'strict' => true,
        ]);
    }

    public function test_non_strict_keeps_invalid_as_is(): void
    {
        $this->assertSame('02188776655', PhoneNumber::normalize('02188776655', [
            'strict' => false,
        ]));
    }

    public function test_disabled_returns_trimmed_input(): void
    {
        $this->assertSame('+98 912', PhoneNumber::normalize('  +98 912  ', [
            'enabled' => false,
        ]));
    }

    public function test_normalize_many(): void
    {
        $this->assertSame(
            ['09123456789', '09121111111'],
            PhoneNumber::normalizeMany(['+989123456789', '9121111111'], [
                'format' => PhoneNumber::FORMAT_LOCAL,
                'strict' => true,
            ]),
        );
    }
}
