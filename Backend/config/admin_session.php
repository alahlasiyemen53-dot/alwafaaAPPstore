<?php

declare(strict_types=1);

return [
    /*
    | Idle timeout in minutes (maps to session.lifetime for admin web).
    | After this period without activity the session expires.
    */
    'idle_timeout_minutes' => (int) env('ADMIN_SESSION_IDLE_TIMEOUT_MINUTES', env('SESSION_LIFETIME', 30)),

    /*
    | Absolute max age of an admin session from login time.
    | Set 0 to disable absolute lifetime enforcement.
    */
    'absolute_lifetime_minutes' => (int) env('ADMIN_SESSION_ABSOLUTE_LIFETIME_MINUTES', 480),

    /*
    | Remember-me for admin web login. Disabled by default for security.
    */
    'remember_me_enabled' => (bool) env('ADMIN_SESSION_REMEMBER_ME_ENABLED', false),

    /*
    | Minutes of recent password confirmation for sensitive actions.
    | Stored as seconds in auth.password_timeout for Laravel RequirePassword.
    */
    'password_confirmation_minutes' => (int) env('ADMIN_SESSION_PASSWORD_CONFIRMATION_MINUTES', 15),

    'session_started_key' => 'admin.session_started_at',
];
