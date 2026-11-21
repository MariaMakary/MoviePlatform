<?php

namespace App\Http\Requests;

/** GET /api/admin/users: optional search by name or email. */
class AdminUsersRequest
{
    public static function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
