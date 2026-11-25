<?php

namespace App\Http\Requests;

final class PasswordRules
{
    // bcrypt only reads the first 72 bytes: a longer password would be cut without telling the user.
    public const MAX_LENGTH = 72;

    /** A password being set: register, reset, change. */
    public const NEW = ['required', 'string', 'min:8', 'max:'.self::MAX_LENGTH, 'confirmed'];

    /** A password being checked: login, current password. Longer than NEW's limit so no existing password is refused. */
    public const MAX_CHECKED_LENGTH = 255;
}
