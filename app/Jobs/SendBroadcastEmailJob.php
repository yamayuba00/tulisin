<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\BroadcastEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendBroadcastEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $subject,
        public string $title,
        public string $message,
    ) {
    }

    public function handle(): void
    {
        Notification::sendNow(
            $this->user,
            new BroadcastEmailNotification($this->subject, $this->title, $this->message),
        );
    }
}
