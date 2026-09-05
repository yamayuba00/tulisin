<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token)
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
        $email = $notifiable->getEmailForPasswordReset();
        $url = rtrim((string) config('app.url'), '/')
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($email);

        return (new MailMessage)
            ->subject('Reset Password Tulisin')
            ->greeting('Halo,')
            ->line('Kamu menerima email ini karena ada permintaan reset password untuk akun Tulisin kamu.')
            ->action('Reset Password', $url)
            ->line('Tautan ini berlaku 60 menit. Abaikan email ini jika kamu tidak meminta reset password.')
            ->salutation('Salam, Tim Tulisin');
    }
}
