<?php

namespace Alizeinodin\SmsApi\Registry;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Drivers\AmootDriver;
use Alizeinodin\SmsApi\Drivers\AvanakDriver;
use Alizeinodin\SmsApi\Drivers\BahmanpayamDriver;
use Alizeinodin\SmsApi\Drivers\BehinpayamDriver;
use Alizeinodin\SmsApi\Drivers\FarapayamakDriver;
use Alizeinodin\SmsApi\Drivers\FarazsmsDriver;
use Alizeinodin\SmsApi\Drivers\GhasedakDriver;
use Alizeinodin\SmsApi\Drivers\IppanelDriver;
use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Drivers\LimosmsDriver;
use Alizeinodin\SmsApi\Drivers\MagfaDriver;
use Alizeinodin\SmsApi\Drivers\MedianaDriver;
use Alizeinodin\SmsApi\Drivers\MelipayamakDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\BaleDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\DiscordDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\EitaaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\FacebookMessengerDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\GapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\IgapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\LineDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\RubikaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\SlackDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\TelegramDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\ViberDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\WhatsAppDriver;
use Alizeinodin\SmsApi\Drivers\NiksmsDriver;
use Alizeinodin\SmsApi\Drivers\PayamaknovinDriver;
use Alizeinodin\SmsApi\Drivers\PayamitoDriver;
use Alizeinodin\SmsApi\Drivers\PayamresanDriver;
use Alizeinodin\SmsApi\Drivers\RastinsmsDriver;
use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use InvalidArgumentException;

final class DriverRegistry
{
    /** @var array<string, class-string<MessageDriver>> */
    private static array $map = [
        'smsir' => SmsIrDriver::class,
        'kavenegar' => KavenegarDriver::class,
        'ghasedak' => GhasedakDriver::class,
        'farazsms' => FarazsmsDriver::class,
        'ippanel' => IppanelDriver::class,
        'magfa' => MagfaDriver::class,
        'niksms' => NiksmsDriver::class,
        'mediana' => MedianaDriver::class,
        'limosms' => LimosmsDriver::class,
        'melipayamak' => MelipayamakDriver::class,
        'farapayamak' => FarapayamakDriver::class,
        'payamito' => PayamitoDriver::class,
        'payamaknovin' => PayamaknovinDriver::class,
        'bahmanpayam' => BahmanpayamDriver::class,
        'amoot' => AmootDriver::class,
        'payamresan' => PayamresanDriver::class,
        'behinpayam' => BehinpayamDriver::class,
        'rastinsms' => RastinsmsDriver::class,
        'avanak' => AvanakDriver::class,
        'telegram' => TelegramDriver::class,
        'whatsapp' => WhatsAppDriver::class,
        'bale' => BaleDriver::class,
        'eitaa' => EitaaDriver::class,
        'rubika' => RubikaDriver::class,
        'gap' => GapDriver::class,
        'igap' => IgapDriver::class,
        'messenger' => FacebookMessengerDriver::class,
        'viber' => ViberDriver::class,
        'line' => LineDriver::class,
        'discord' => DiscordDriver::class,
        'slack' => SlackDriver::class,
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(string $type, array $config = []): MessageDriver
    {
        $class = self::$map[$type] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException(sprintf('Unknown SMS driver [%s].', $type));
        }

        $config = array_merge(['driver' => $type, 'name' => $type], $config);

        return new $class($config);
    }

    /**
     * @return array<string, class-string<MessageDriver>>
     */
    public static function all(): array
    {
        return self::$map;
    }

    public static function has(string $type): bool
    {
        return isset(self::$map[$type]);
    }

    /**
     * @param  class-string<MessageDriver>  $class
     */
    public static function extend(string $type, string $class): void
    {
        if (! is_subclass_of($class, MessageDriver::class)) {
            throw new SmsApiException(sprintf('%s must implement MessageDriver.', $class));
        }

        self::$map[$type] = $class;
    }
}
