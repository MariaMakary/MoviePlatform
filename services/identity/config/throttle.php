<?php

// Attempts allowed per email address on the public auth endpoints (task 1287).
// The Gateway calls Identity from the server, so the client IP here is always the Gateway's:
// the per-IP limit lives in the Gateway forms, this one protects each account.
return [
    // A successful login clears the counter, so only failed attempts add up.
    'login' => ['attempts' => 5, 'minutes' => 1, 'clear_on_success' => true],
    'register' => ['attempts' => 5, 'minutes' => 10, 'clear_on_success' => false],
    'password_forgot' => ['attempts' => 3, 'minutes' => 10, 'clear_on_success' => false],
    'password_reset' => ['attempts' => 5, 'minutes' => 10, 'clear_on_success' => true],
];
