<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $title,
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
        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.broadcast', [
                'title' => $this->title !== '' ? $this->title : $this->subject,
                'content' => $this->absolutizeImages($this->message),
            ]);
    }

    /**
     * Ubah src gambar relatif (/api/...) menjadi URL absolut agar bisa dimuat
     * oleh email client. URL relatif hanya valid di dalam SPA, bukan di email.
     */
    private function absolutizeImages(string $html): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $host = parse_url($base, PHP_URL_HOST);

        // Pastikan HTTPS di luar localhost agar tidak kena mixed content.
        if ($host && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $base = preg_replace('#^http://#i', 'https://', $base);
        }

        return preg_replace_callback(
            '#(src=["\'])/(api/(?:blast-images|media)/[^"\']+)(["\'])#i',
            function (array $m) use ($base): string {
                return $m[1] . $base . '/' . ltrim($m[2], '/') . $m[3];
            },
            $html,
        );
    }
}
