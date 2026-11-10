<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarEventRequest;
use App\Http\Resources\CalendarLinkResource;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function ics(Request $request): Response
    {
        $event = $this->validate($request, CalendarEventRequest::rules());

        return response($this->calendar->ics($event), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->calendar->filename($event).'"',
        ]);
    }

    public function googleLink(Request $request): JsonResponse
    {
        $event = $this->validate($request, CalendarEventRequest::rules());

        return (new CalendarLinkResource($this->calendar->googleLink($event)))->response();
    }
}
