<?php

// Gateway rate limits (task 1287). Identity limits the same forms per email (services/identity/config/throttle.php);
// these limit them per client IP, which Identity cannot see because only the Gateway talks to it.
return [
    // Every web request (pages and Livewire updates), per logged-in user or per IP.
    'web' => ['attempts' => 300, 'minutes' => 1],

    // Per IP, and a class on one university Wi-Fi shares an IP: a successful login clears the counter (only failed
    // logins add up), and the other limits leave room for a whole class signing up at once.
    'login' => ['attempts' => 10, 'minutes' => 1],
    'register' => ['attempts' => 30, 'minutes' => 10],
    'password_forgot' => ['attempts' => 10, 'minutes' => 10],
    'password_reset' => ['attempts' => 10, 'minutes' => 10],
];
