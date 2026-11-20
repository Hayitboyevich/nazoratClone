<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramLogNotification extends Notification
{
    use Queueable;


    public function __construct(protected string $message)
    {

    }

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram($notifiable): TelegramMessage
    {

        return TelegramMessage::create()
            ->token(config('app.telegram.token'))
            ->to(config('app.telegram.chat_id'))
            ->content($this->message)
            ->parseMode('HTML');
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
