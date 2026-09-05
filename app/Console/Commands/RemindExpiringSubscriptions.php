<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionReminderNotification;
use Illuminate\Console\Command;

class RemindExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:remind-expiring';

    protected $description = 'Kirim email pengingat 5 hari sebelum masa langganan berakhir.';

    public function handle(): int
    {
        $target = now()->addDays(5);

        $subscriptions = Subscription::with('user')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', $target)
            ->whereNull('reminded_at')
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->user) {
                continue;
            }

            $subscription->user->notify(
                new SubscriptionReminderNotification($subscription->ends_at->format('d M Y H:i')),
            );

            $subscription->forceFill(['reminded_at' => now()])->save();
        }

        $this->info("Mengirim pengingat ke {$subscriptions->count()} langganan.");

        return self::SUCCESS;
    }
}
