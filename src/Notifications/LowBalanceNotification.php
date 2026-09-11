<?php

namespace Alizeinodin\SmsApi\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public float $balance,
        public float $threshold,
        public string $driver,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = config('smsapi.balance_alert.channels', []);

        if (is_string($channels)) {
            $channels = array_map('trim', explode(',', $channels));
        }

        $channels = array_values(array_filter(
            (array) $channels,
            static fn ($channel) => is_string($channel) && $channel !== '' && $channel !== 'log'
        ));

        if ($notifiable instanceof AnonymousNotifiable) {
            return array_values(array_intersect($channels, array_keys($notifiable->routes)));
        }

        return array_values(array_intersect($channels, ['mail', 'sms', 'database']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('هشدار کمبود اعتبار پنل پیامک')
            ->greeting('هشدار موجودی')
            ->line("اعتبار درایور [{$this->driver}] به {$this->formatAmount($this->balance)} رسیده است.")
            ->line("آستانه هشدار تنظیم‌شده: {$this->formatAmount($this->threshold)}")
            ->line('لطفاً کیف‌پول پنل پیامک را شارژ کنید.');
    }

    public function toSms(object $notifiable): NotificationMessage
    {
        return NotificationMessage::create(
            "هشدار SMS: اعتبار {$this->driver}={$this->formatAmount($this->balance)} (حداقل {$this->formatAmount($this->threshold)})"
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sms_low_balance',
            'driver' => $this->driver,
            'balance' => $this->balance,
            'threshold' => $this->threshold,
            'message' => "اعتبار پنل پیامک [{$this->driver}] کمتر از آستانه است.",
        ];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }
}
