<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class EmailVerificationNotification extends Notification
{
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
            ->subject('Verifikasi Email Tulisin')
            ->view('emails.verify-email', [
                'name' => $notifiable->name,
                'url' => $this->verificationUrl($notifiable),
            ]);
    }

    /**
     * Tautan verifikasi bertanda tangan dengan masa berlaku 12 jam.
     */
    private function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 720)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
