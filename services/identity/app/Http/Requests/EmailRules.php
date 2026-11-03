<?php

namespace App\Http\Requests;

final class EmailRules
{
    // Laravel 10's email rule lets line breaks through (CVE-2026-48019): they could add mail headers such as Bcc.
    // Every email field uses this rule; see services/identity/CLAUDE.md before removing it.
    public const NO_CONTROL_CHARACTERS = 'not_regex:/[\x00-\x1F\x7F]/';
}
