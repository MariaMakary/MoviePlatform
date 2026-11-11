<?php

namespace MoviePlatform\Common\MovieNights;

enum ParticipantStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
