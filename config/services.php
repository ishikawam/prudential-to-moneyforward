<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // プルデンシャル生命保険
    'prudential' => [
        'login_id' => env('PRUDENTIAL_LOGIN_ID'),  // ログインID
        'password' => env('PRUDENTIAL_PASSWORD'),  // パスワード
        'birth_year' => env('PRUDENTIAL_BIRTH_YEAR'),  // 生年月日(年)
        'birth_month' => env('PRUDENTIAL_BIRTH_MONTH'),  // 生年月日(月)
        'birth_day' => env('PRUDENTIAL_BIRTH_DAY'),  // 生年月日(日)
    ],

    // マネーフォワード
    'money_forward' => [
        'email' => env('MONEYFORWARD_EMAIL'),  // メールアドレス
        'password' => env('MONEYFORWARD_PASSWORD'),  // パスワード
        'name' => env('MONEYFORWARD_NAME'),  // 金融機関名 (手元の現金・資産 - 未対応のその他保有資産)
    ],
];
