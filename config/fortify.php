<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email',
    'views' => true, 'home' => '/inicio', 'middleware' => ['web'],
    'lowercase_usernames' => true,
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'features' => [
        Features::resetPasswords(), Features::emailVerification(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
    'redirects' => ['logout' => '/login', 'password-reset' => '/login', 'password-confirmation' => '/seguranca'],
];
