<?php

namespace App\Enums;

enum MovieNightStatus: string
{
    case Open = 'open';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
}
