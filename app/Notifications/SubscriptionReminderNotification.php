<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReminderNotification extends Notification
{
    public function __construct(public string $endsAt)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/') . '/apps/u/topup';

        return (new MailMessage)
            ->subject('Langganan Tulisin kamu segera berakhir')
            ->greeting('Halo,')
            ->line('Masa langganan kamu akan berakhir pada ' . $this->endsAt . '.')
            ->line('Perpanjang sekarang agar fitur download, Agent Canvas, dan Turnitin tetap aktif.')
            ->action('Perpanjang Langganan', $url)
            ->salutation('Salam, Tim Tulisin');
    }
}
