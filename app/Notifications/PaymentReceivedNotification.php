<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    public function __construct(public Payment $payment)
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
        $payment = $this->payment->load('user');
        $user = $payment->user;

        return (new MailMessage)
            ->subject('Pembayaran baru diterima — ' . $payment->invoice_number)
            ->greeting('Halo Admin,')
            ->line('Ada pembayaran baru yang sudah diterima.')
            ->line('Invoice: ' . $payment->invoice_number)
            ->line('Pelanggan: ' . ($user?->name ?? '-') . ' (' . ($user?->email ?? '-') . ')')
            ->line('Metode: ' . $payment->method)
            ->line('Nominal: Rp ' . number_format((float) $payment->amount, 0, ',', '.'))
            ->salutation('Salam, Sistem Tulisin');
    }
}
