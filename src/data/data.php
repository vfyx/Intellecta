<?php

declare(strict_types=1);

return [
    'people' => require __DIR__ . '/community/people.php',
    'feed' => require __DIR__ . '/community/feed.php',
    'notifications' => require __DIR__ . '/system/notifications.php',
    'messages' => require __DIR__ . '/messaging/messages.php',
    'account' => [
        'name' => 'Miguel Barbosa',
        'username' => 'otherdaujune',
        'class' => '2MIN',
        'birthdate' => '2010-06-29',
        'birthdate_label' => '29 de junho de 2010',
        'gender' => '',
        'phone' => '(13) 98230-3725',
        'email' => 'miguelmucci290610@gmail.com',
        'country' => 'Brasil',
        'bio' => '',
    ],
];
