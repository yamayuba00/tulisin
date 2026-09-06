<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification
{
    public function __construct(public Subscription $subscription)
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
        return (new MailMessage)
            ->subject('Langganan Tulisin Kamu Aktif')
            ->view('emails.subscription-activated', [
                'name' => $notifiable->name,
                'ends_at' => $this->subscription->ends_at?->format('d M Y H:i') ?? '-',
                'price' => $this->subscription->price,
            ]);
    }
}
