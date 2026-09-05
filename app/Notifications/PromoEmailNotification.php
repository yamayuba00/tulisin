<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromoEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $message,
    ) {
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
        $lines = preg_split('/\r\n|\r|\n/', trim($this->message)) ?: ['Ada promo terbaru untuk kamu.'];

        $mail = (new MailMessage)->subject($this->subject);
        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail->salutation('Salam, Tim Tulisin');
    }
}
