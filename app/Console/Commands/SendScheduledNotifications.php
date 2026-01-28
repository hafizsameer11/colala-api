<?php

namespace App\Console\Commands;

use App\Models\SystemPushNotification;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled notifications that are due';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled notifications...');

        // Get all scheduled notifications that are due (scheduled_for <= now)
        $scheduledNotifications = SystemPushNotification::where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->get();

        if ($scheduledNotifications->isEmpty()) {
            $this->info('No scheduled notifications found that are due.');
            return 0;
        }

        $this->info("Found {$scheduledNotifications->count()} scheduled notification(s) to send.");

        $controller = new AdminNotificationController();
        $sentCount = 0;
        $failedCount = 0;

        foreach ($scheduledNotifications as $notification) {
            try {
                $this->info("Sending notification ID: {$notification->id} - '{$notification->title}'");

                // Call the sendNotification method directly (it's public)
                $controller->sendNotification($notification);

                $sentCount++;
                $this->info("✓ Notification ID {$notification->id} sent successfully.");
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Failed to send notification ID {$notification->id}: " . $e->getMessage());
                Log::error('Failed to send scheduled notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("\nSummary:");
        $this->info("  Sent: {$sentCount}");
        $this->info("  Failed: {$failedCount}");

        return 0;
    }
}

