<?php

return [
    'require_two_factor' => (bool) env('HMS_REQUIRE_TWO_FACTOR', env('APP_ENV') === 'production'),
    'login_max_attempts' => (int) env('HMS_LOGIN_MAX_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('HMS_LOCKOUT_MINUTES', 15),
];
