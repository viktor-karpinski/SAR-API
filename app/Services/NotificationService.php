<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FcmToken;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging;

class NotificationService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function sendEventNotification(Event $event)
    {
        $authUserId = Auth::check() ? Auth::id() : null;

        $userTokens = FcmToken::when($authUserId, fn($q) => $q->where('user_id', '!=', $authUserId))->get();

        foreach ($userTokens as $user) {
            $message = CloudMessage::fromArray([
                'token' => $user->token,
                'notification' => [
                    'title' => $event->status === 'active' ? 'Zásah sa začal!' : 'Zásah čoskoro! Buď pripravení!',
                    'body' => "Poloha: {$event->address}",
                    'sound' => $event->status === 'active' ? 'default' : 'siren_alarm.caf'
                ],
                'android' => [
                    'notification' => [
                        'sound' => $event->status === 'active' ? 'default' : 'siren_alarm',
                        'channel_id' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => $event->status === 'active' ? 'default' : 'siren_alarm.caf',
                        ],
                    ],
                ],
            ]);

            $this->messaging->send($message);
        }
    }
}
