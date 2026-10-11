<?php

/*
|--------------------------------------------------------------------------
| Mail (disabled, ADR-0005)
|--------------------------------------------------------------------------
|
| TravelEasy sends no e-mail in any environment (docs/adr/0005-no-outgoing-email.md).
| The default mailer is fixed to `array` and deliberately NOT read from env, and it is
| the only mailer defined, so neither application code nor a third-party package can
| send anything off the server. Do not add MAIL_* variables or other transports.
| Because Laravel merges its own default mailers into this file, AppServiceProvider
| additionally pins mail.default and mail.mailers at runtime.
|
*/

return [

    'default' => 'array',

    'mailers' => [

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => null,
        'name' => null,
    ],

];
