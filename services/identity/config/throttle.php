<?php

// Identity's limits for the 'throttle:<name>' middleware of packages/common (task 1287), added to its 'api' limit.
// Per email, not per IP: the Gateway calls Identity from the server, so the client IP here is always the Gateway's
// (the per-IP limit is in the Gateway forms). Uses the Redis cache like the other services (CACHE_DRIVER=redis).
return [
    // A successful login clears the counter, so only failed attempts add up.
    'login' => ['attempts' => 5, 'minutes' => 1, 'by' => 'email', 'clear_on_success' => true],
    'register' => ['attempts' => 5, 'minutes' => 10, 'by' => 'email', 'clear_on_success' => false],
    'password_forgot' => ['attempts' => 3, 'minutes' => 10, 'by' => 'email', 'clear_on_success' => false],
    'password_reset' => ['attempts' => 5, 'minutes' => 10, 'by' => 'email', 'clear_on_success' => true],
];
