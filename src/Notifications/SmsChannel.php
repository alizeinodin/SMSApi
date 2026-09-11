<?php

namespace Alizeinodin\SmsApi\Notifications;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\SmsManager;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsManager $manager)
    {
    }

    /**
     * Send the given notification.
     *
     * @throws SmsApiException
     */
    public function send(object $notifiable, Notification $notification): ?SendResult
    {
        if (! method_exists($notification, 'toSms')) {
            return null;
        }

        $message = $notification->toSms($notifiable);

        if (is_string($message)) {
            $message = NotificationMessage::create($message);
        }

        if (! $message instanceof NotificationMessage) {
            throw new SmsApiException(
                'Notification toSms() must return an NotificationMessage instance or string.'
            );
        }

        $to = $message->getTo() ?? $this->recipient($notifiable, $notification);

        if ($to === null || $to === '') {
            return null;
        }

        $this->assertMessagePolicyWindow($message);

        if ($message->getQueue() !== null) {
            return $this->queue($message, $to);
        }

        $driver = $this->resolveDriver($message);

        if ($message->isTemplate()) {
            $templateId = $message->getTemplateId();

            if ($templateId === null) {
                throw new SmsApiException('NotificationMessage sendTemplate() requires a template id.');
            }

            return $driver->sendTemplate(
                $to,
                $templateId,
                $message->getParameters(),
                $message->getOptions(),
            );
        }

        $content = $message->getContent();

        if ($content === null || $content === '') {
            throw new SmsApiException('NotificationMessage content is required for plain SMS notifications.');
        }

        return $driver->send($to, $content, $message->getOptions());
    }

    private function queue(NotificationMessage $message, string $to): SendResult
    {
        $pending = $this->manager->queue($message->getQueue());

        $failover = $message->getFailoverDrivers();
        if ($failover !== null) {
            $pending = $pending->failover($failover);
        } elseif ($message->getDriver() !== null) {
            $pending = $pending->driver($message->getDriver());
        }

        if ($message->getQueueDelay() !== null) {
            $pending = $pending->delay($message->getQueueDelay());
        }

        if ($message->getTries() !== null) {
            $pending = $pending->tries($message->getTries());
        }

        if ($message->getBackoff() !== []) {
            $pending = $pending->backoff($message->getBackoff());
        }

        if ($message->getTimeout() !== null) {
            $pending = $pending->timeout($message->getTimeout());
        }

        if ($message->isTemplate()) {
            $templateId = $message->getTemplateId();

            if ($templateId === null) {
                throw new SmsApiException('NotificationMessage sendTemplate() requires a template id.');
            }

            $pending->sendTemplate(
                $to,
                $templateId,
                $message->getParameters(),
                $this->withChannel($message, $message->getOptions()),
            );
        } else {
            $content = $message->getContent();

            if ($content === null || $content === '') {
                throw new SmsApiException('NotificationMessage content is required for plain SMS notifications.');
            }

            $pending->send($to, $content, $this->withChannel($message, $message->getOptions()));
        }

        return new SendResult(
            success: true,
            message: 'Queued',
            data: [
                'queue' => $this->manager->resolveQueueName($message->getQueue()),
            ],
            driver: 'queue',
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function withChannel(NotificationMessage $message, array $options): array
    {
        if ($message->getQueue() !== null) {
            $options['channel'] = $options['channel'] ?? $message->getQueue();
        }

        return $options;
    }

    private function resolveDriver(NotificationMessage $message): SmsDriver
    {
        $failover = $message->getFailoverDrivers();

        if ($failover !== null) {
            return $this->manager->failover($failover);
        }

        $driver = $message->getDriver();

        if ($driver !== null) {
            return $this->manager->driver($driver);
        }

        return $this->manager->driver();
    }

    /**
     * Enforce allowed / quiet hours when NotificationMessage::message($type) set message_type.
     */
    private function assertMessagePolicyWindow(NotificationMessage $message): void
    {
        $type = $message->getOptions()['message_type'] ?? null;

        if (! is_string($type) || $type === '') {
            return;
        }

        /** @var MessagePolicyRegistry $registry */
        $registry = app(MessagePolicyRegistry::class);

        if (! $registry->has($type)) {
            return;
        }

        $registry->get($type)->assertWithinAllowedWindow();
    }

    private function recipient(object $notifiable, Notification $notification): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $route = $notifiable->routeNotificationForSms($notification);

            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('sms', $notification);

            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        foreach (['mobile', 'phone', 'phone_number'] as $attribute) {
            if (isset($notifiable->{$attribute}) && is_scalar($notifiable->{$attribute})) {
                $value = trim((string) $notifiable->{$attribute});

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
