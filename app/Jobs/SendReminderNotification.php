<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventUser;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function handle(NotificationService $notificationService)
    {
        $event = Event::with('eventUsers.user')->find($this->event->id);

        if (!$event || $event->till !== null) {
            return;
        }

        $usersToRemind = $event->eventUsers->where('status', 0);

        if ($usersToRemind->isEmpty()) {
            return;
        }

        Log::info("Sending reminder to " . $usersToRemind->count() . " users");

        $notificationService->sendEventNotification($event);

        // Re-dispatch if still unanswered users
        SendReminderNotification::dispatch($event)->delay(now()->addSeconds(30));
    }
}
