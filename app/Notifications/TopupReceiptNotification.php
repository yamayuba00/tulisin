<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\TopupOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TopupReceiptNotification extends Notification
{
    public function __construct(
        public Payment $payment,
        public TopupOrder $order,
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
        return (new MailMessage)
            ->subject('Pembelian Koin Tulisin Berhasil')
            ->view('emails.topup-receipt', [
                'name' => $notifiable->name,
                'invoice' => $this->payment->invoice_number,
                'credits' => $this->order->credits,
                'amount' => $this->order->amount,
                'total' => $this->payment->amount,
                'date' => now()->format('d M Y H:i'),
            ]);
    }
}
