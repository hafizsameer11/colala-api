<?php

namespace App\Jobs;

use App\Models\SystemPushNotification;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes timeout

    /**
     * Create a new job instance.
     */
    public function __construct(public int $notificationId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Load the notification
            $notification = SystemPushNotification::find($this->notificationId);

            if (!$notification) {
                Log::error('Scheduled notification not found', [
                    'notification_id' => $this->notificationId
                ]);
                return;
            }

            // Check if notification is still scheduled (might have been sent manually)
            if ($notification->status !== 'scheduled') {
                Log::info('Notification is no longer scheduled, skipping', [
                    'notification_id' => $this->notificationId,
                    'current_status' => $notification->status
                ]);
                return;
            }

            // Check if scheduled time has arrived
            if ($notification->scheduled_for && $notification->scheduled_for->isFuture()) {
                Log::info('Notification scheduled time has not arrived yet, skipping', [
                    'notification_id' => $this->notificationId,
                    'scheduled_for' => $notification->scheduled_for,
                    'now' => now()
                ]);
                return;
            }

            Log::info('Processing scheduled notification', [
                'notification_id' => $this->notificationId,
                'title' => $notification->title
            ]);

            // Send the notification
            $controller = new AdminNotificationController();
            $controller->sendNotification($notification);

            Log::info('Scheduled notification sent successfully', [
                'notification_id' => $this->notificationId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send scheduled notification', [
                'notification_id' => $this->notificationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update notification status to failed
            $notification = SystemPushNotification::find($this->notificationId);
            if ($notification) {
                $notification->update(['status' => 'failed']);
            }

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Scheduled notification job failed after all retries', [
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage()
        ]);

        // Update notification status to failed
        $notification = SystemPushNotification::find($this->notificationId);
        if ($notification && $notification->status === 'scheduled') {
            $notification->update(['status' => 'failed']);
        }
    }
}

