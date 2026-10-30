<?php

namespace MoviePlatform\Common\Auth;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}
