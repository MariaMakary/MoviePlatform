<?php

namespace MoviePlatform\Common\MovieNights;

enum MovieNightStatus: string
{
    case Open = 'open';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
}
