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
            ->view('emails.reset-password', [
                'name' => $notifiable->name,
                'url' => $url,
            ]);
    }
}
