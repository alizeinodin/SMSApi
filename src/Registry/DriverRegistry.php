<?php

namespace Alizeinodin\SmsApi\Registry;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Drivers\AmootDriver;
use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use Alizeinodin\SmsApi\Drivers\AvanakDriver;
use Alizeinodin\SmsApi\Drivers\BahmanpayamDriver;
use Alizeinodin\SmsApi\Drivers\BehinpayamDriver;
use Alizeinodin\SmsApi\Drivers\FarapayamakDriver;
use Alizeinodin\SmsApi\Drivers\FarazsmsDriver;
use Alizeinodin\SmsApi\Drivers\GhasedakDriver;
use Alizeinodin\SmsApi\Drivers\IppanelDriver;
use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Drivers\LimosmsDriver;
use Alizeinodin\SmsApi\Drivers\LogTransport;
use Alizeinodin\SmsApi\Drivers\MagfaDriver;
use Alizeinodin\SmsApi\Drivers\MedianaDriver;
use Alizeinodin\SmsApi\Drivers\MelipayamakDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\BaleDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\DiscordDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\EitaaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\GapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\IgapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\LineDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\FacebookMessengerDriver;
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

/**
 * Maps transport keys to driver classes — extend via register() in a service provider.
 */
final class DriverRegistry
{
    /** @var array<string, class-string<MessageDriver>> */
    private static array $map = [
        'smsir' => SmsIrDriver::class,
        'kavenegar' => KavenegarDriver::class,
        'ghasedak' => GhasedakDriver::class,
        'melipayamak' => MelipayamakDriver::class,
        'farapayamak' => FarapayamakDriver::class,
        'payamito' => PayamitoDriver::class,
        'payamaknovin' => PayamaknovinDriver::class,
        'bahmanpayam' => BahmanpayamDriver::class,
        'payamresan' => PayamresanDriver::class,
        'behinpayam' => BehinpayamDriver::class,
        'rastinsms' => RastinsmsDriver::class,
        'farazsms' => FarazsmsDriver::class,
        'niksms' => NiksmsDriver::class,
        'magfa' => MagfaDriver::class,
        'avanak' => AvanakDriver::class,
        'amoot' => AmootDriver::class,
        'ippanel' => IppanelDriver::class,
        'limosms' => LimosmsDriver::class,
        'mediana' => MedianaDriver::class,
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
        'log' => LogTransport::class,
        'array' => ArrayDriver::class,
    ];

    /**
     * @param array<string, mixed> $config
     * @throws SmsApiException
     */
    public static function make(string $type, array $config): MessageDriver
    {
        $class = self::$map[$type] ?? null;

        if ($class === null || ! is_subclass_of($class, MessageDriver::class)) {
            throw new SmsApiException("SMS transport [{$type}] is not registered.");
        }

        return new $class($config);
    }

    /**
     * @param  class-string<MessageDriver>  $class
     */
    public static function register(string $type, string $class): void
    {
        self::$map[$type] = $class;
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
}
