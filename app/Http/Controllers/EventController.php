<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventUser;
use App\Models\FcmToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging;


class EventController extends Controller
{

    private $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    private function getEvent($event)
    {
        return [
            'id' => $event->id,
            'address' => $event->address,
            'lat' => $event->lat,
            'lon' => $event->lon,
            'description' => $event->description,
            'from' => $event->from,
            'till' => $event->till,
            'status' => $event->status,
            'user_id' => $event->user_id,
            'created_at' => Carbon::parse($event->created_at)->format('d.m.Y'),
            'updated_at' => $event->updated_at,
            'users' => $event->eventUsers->map(function ($eventUser) {
                return [
                    'id' => $eventUser->id,
                    'status' => $eventUser->status,
                    'user' => $eventUser->user
                ];
            }),
        ];
    }

    public function index()
    {
        $events = Event::orderBy('id', 'DESC')->get()->map(function ($event) {
            return $this->getEvent($event);
        });

        return response()->json($events, 200);
    }


    public function store(Request $request)
    {
        if (!Auth::user()->isOrganiser) {
            return response()->json([
                'message' => 'STORING EVENT FAILED => NOT AN ORGANISER'
            ], 403);
        }

        $request->validate([
            'address' => 'required|string|max:255',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'description' => 'nullable|string',
            //'from' => 'required|date_format:H:i',
            //'till' => 'nullable|date_format:H:i',
            //'status' => 'nullable|string|in:pending,active,completed',
        ]);

        $event = Event::create([
            'address' => $request->address,
            'lat' => -1, //$request->lat,
            'lon' => -1, //$request->lon,
            'description' => $request->description,
            'from' => Carbon::now(),
            'till' => null,
            'user_id' => Auth::user()->id,
        ]);

        $event->refresh();
        $users = User::where([
            ['id', '!=', Auth::user()->id],
            ['disabled', false]
        ])->get();

        foreach ($users as $user) {
            EventUser::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'status' => 0,
            ]);
        }

        $event->load(['eventUsers.user']);

        $this->sendPushNotification($event);
        $this->sendPushNotification($event);
        $this->sendPushNotification($event);

        return response()->json($this->getEvent($event), 201);
    }

    public function update(Request $request, Event $event)
    {
        if (!Auth::user()->isOrganiser) {
            return response()->json([
                'message' => 'STORING EVENT FAILED => NOT AN ORGANISER'
            ], 403);
        }

        $validatedData = $request->validate([
            'address' => 'sometimes|string|max:255',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $event->update($validatedData);

        return response()->json($event, 200);
    }

    public function activate(Event $event)
    {
        if (!Auth::user()->isOrganiser) {
            return response()->json([
                'message' => 'ACTIVATING EVENT FAILED => NOT AN ORGANISER'
            ], 403);
        }

        $event->status = 'active';
        $event->save();

        $this->sendPushNotification($event);

        return response()->json([
            'event' => $this->getEvent($event),
        ], 200);
    }

    public function destroy(Event $event)
    {
        if (!Auth::user()->isOrganiser) {
            return response()->json([
                'message' => 'DELETING EVENT FAILED => NOT AN ORGANISER'
            ], 403);
        }

        $event->eventUsers()->delete();
        $event->delete();

        $events = Event::orderBy('id', 'DESC')->get()->map(function ($event) {
            return $this->getEvent($event);
        });

        return response()->json(['events' => $events], 200);
    }

    public function finishEvent(Event $event)
    {
        if (!Auth::user()->isOrganiser) {
            return response()->json([
                'message' => 'FINISHING EVENT FAILED => NOT AN ORGANISER'
            ], 403);
        }

        if ($event->till) {
            return response()->json($event, 200);
        }

        $event->till = Carbon::now();
        $event->save();
        foreach ($event->eventUsers as $user) {
            if ($user->status == 0) {
                $user->status = 2;
                $user->save();
            }
        }

        $events = Event::orderBy('id', 'DESC')->get()->map(function ($event) {
            return $this->getEvent($event);
        });

        return response()->json([
            'event' => $this->getEvent($event),
            'events' => $events,
        ], 200);
    }

    private function userParticipation(Event $event, $status)
    {
        if ($event->till != null) {
            return response()->json([], 400);
        }

        $user = EventUser::where([
            ['event_id', $event->id],
            ['user_id', Auth::user()->id],
        ])->first();

        $user->status = $status;
        $user->save();

        return response()->json($this->getEvent($event), 200);
    }

    public function declineParticipation(Event $event)
    {
        return $this->userParticipation($event, 2);
    }

    public function acceptParticipation(Event $event)
    {
        return $this->userParticipation($event, 1);
    }

    private function sendPushNotification($event)
    {
        $user_tokens = FcmToken::where('user_id', '!=', Auth::user()->id)->get();

        foreach ($user_tokens as $user) {
            $message = CloudMessage::fromArray([
                'token' => $user->token,
                'notification' => [
                    'title' => $event->status == 'active' ? 'Zásah sa začal!' : 'Zásah čoskoro! Buď pripravení!',
                    'body' => "Poloha: {$event->address}",
                    'sound' => $event->status == 'active' ? 'default' : 'siren_alarm.caf'
                ],
                'android' => [
                    'notification' => [
                        'sound' => $event->status == 'active' ? 'default' : 'siren_alarm',
                        'channel_id' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => $event->status == 'active' ? 'default' : 'siren_alarm.caf',
                        ],
                    ],
                ],
            ]);

            $this->messaging->send($message);
        }
    }

    public function show(Event $event)
    {
        return response()->json($this->getEvent($event));
    }
}
