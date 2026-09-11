<?php

namespace Alizeinodin\SmsApi\Facades;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Pending\QueuedSend;
use Alizeinodin\SmsApi\SmsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Alizeinodin\SmsApi\Testing\SmsFake fake()
 * @method static void unfake()
 * @method static bool isFaking()
 * @method static list<\Alizeinodin\SmsApi\Testing\RecordedMessage> recorded()
 * @method static void assertSent(callable|null $callback = null, int|null $times = null)
 * @method static void assertNotSent(callable|null $callback = null)
 * @method static void assertNothingSent()
 * @method static void assertSentTimes(int $times)
 * @method static void assertSentTo(string $mobile, callable|null $callback = null)
 * @method static void assertTemplateSent(string|null $mobile = null, int|string|null $templateId = null, callable|null $callback = null)
 * @method static MessageDriver driver(string|null $driver = null)
 * @method static MessageDriver connection(string|null $name = null)
 * @method static SmsDriver smsDriver(string|null $name = null)
 * @method static MessageDriver failover(array|null $drivers = null)
 * @method static \Alizeinodin\SmsApi\Pending\TemplateSend template(string $name)
 * @method static \Alizeinodin\SmsApi\Pending\TemplateSend otp(string $name)
 * @method static \Alizeinodin\SmsApi\Otp\OtpIssue challenge(string $purpose)
 * @method static \Alizeinodin\SmsApi\Otp\OtpVault otpVault()
 * @method static array otpChannels()
 * @method static \Alizeinodin\SmsApi\Templates\TemplateRegistry templates()
 * @method static \Alizeinodin\SmsApi\Pending\PolicySend policy(string $type)
 * @method static \Alizeinodin\SmsApi\Policies\MessagePolicyRegistry policies()
 * @method static QueuedSend queue(string|null $name = null)
 * @method static QueuedSend onQueue(string $queue)
 * @method static QueuedSend later(\DateTimeInterface|\DateInterval|int $delay, string|null $queue = null)
 * @method static string resolveQueueName(string|null $name = null)
 * @method static string getDefaultDriver()
 * @method static SmsManager extend(string $driver, \Closure $callback)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult send(string|array $mobiles, string $message, array $options = [])
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult bulk(string $message, array $mobiles, array $options = [])
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult sendMapped(array $messages, array $mobiles, array $options = [])
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = [])
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult cancelScheduled(string $packId)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getMessage(int $messageId)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getPack(string $packId)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getPacks(int $pageNumber = 1, int $pageSize = 100)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getLiveSends(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, bool $sortByNewest = false)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getArchivedSends(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, ?int $fromDate = null, ?int $toDate = null, bool $sortByNewest = false)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getLatestReceived(int $count = 100)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getLiveReceived(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, bool $sortByNewest = false)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getArchivedReceived(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, ?int $fromDate = null, ?int $toDate = null)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getCredit()
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult getLines()
 * @method static SmsDriver setLineNumber(int|string $lineNumber)
 * @method static int|null getLineNumber()
 *
 * @see \Alizeinodin\SmsApi\SmsManager
 * @see \Alizeinodin\SmsApi\Pending\QueuedSend
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
