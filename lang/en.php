<?php

declare(strict_types=1);

/**
 * Interface strings — English. Keys mirror lang/fr.php (French is the fallback language).
 */

return [
    'common' => [
        'skip_to_content' => 'Skip to content',
        'back_home' => 'Back to homepage',
        'price_on_request' => 'Price on request',
        'price_period' => [
            'month' => '/ month',
            'week' => '/ week',
            'night' => '/ night',
            'year' => '/ year',
        ],
    ],

    'errors' => [
        'eyebrow' => 'Error :code',
        'back_dashboard' => 'Back to dashboard',
        'reload' => 'Reload the page',
        403 => [
            'title' => 'Access denied',
            'text' => 'You do not have permission to view this page.',
        ],
        404 => [
            'title' => 'Page not found',
            'text' => 'This address does not exist or has moved. The listing you are looking for may have been sold or rented.',
        ],
        405 => [
            'title' => 'Action not allowed',
            'text' => 'This address cannot be used this way.',
        ],
        419 => [
            'title' => 'Session expired',
            'text' => 'The form was left open for too long. Reload the page, then submit it again.',
        ],
        429 => [
            'title' => 'Too many attempts',
            'text' => 'Please wait a few minutes before trying again.',
        ],
        500 => [
            'title' => 'Something went wrong',
            'text' => 'The problem has been logged and will be fixed. Please try again shortly.',
        ],
        503 => [
            'title' => 'Coming soon',
            'text' => 'immobilier.abidjan.net opens soon. Please come back in a few days.',
        ],
    ],
];
