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

    'auth' => [
        'aside' => [
            'eyebrow' => 'Management area',
            'headline' => 'Every published listing is a verified listing.',
            'fact_1' => 'Each listing is reviewed before going live',
            'fact_2' => 'Selected partner agencies',
            'fact_3' => 'Côte d’Ivoire · pan-African expansion',
        ],
        'roles' => [
            'super_admin' => 'Super administrator',
            'country_admin' => 'Country administrator',
            'agency_owner' => 'Agency manager',
            'agency_agent' => 'Agency agent',
        ],
        'email' => 'Email address',
        'password_label' => 'Password',
        'show_password' => 'Show password',
        'back_to_login' => 'Back to sign in',
        'login' => [
            'title' => 'Sign in',
            'subtitle' => 'Access your listing management area.',
            'forgot' => 'Forgot password?',
            'remember' => 'Keep me signed in on this device',
            'submit' => 'Sign in',
            'partner' => 'Real estate agency?',
            'partner_link' => 'Become a partner',
            'invalid' => 'Incorrect email address or password.',
            'required' => 'Enter your email address and password.',
            'locked' => 'Too many attempts. Try again in :minutes minutes or reset your password.',
            'wrong_site' => 'This account has no access to this country’s site.',
            'expired' => 'Your session expired after a period of inactivity. Please sign in again.',
            'logged_out' => 'You are signed out.',
        ],
        'logout' => 'Sign out',
        'forgot' => [
            'title' => 'Forgot password',
            'subtitle' => 'Enter your account email address and we will send you a link to choose a new password.',
            'submit' => 'Send the link',
            'sent' => 'If an account matches this address, an email has just been sent. The link is valid for :minutes minutes.',
            'too_many' => 'Too many requests from your connection. Please try again in a few minutes.',
            'invalid_email' => 'Enter a valid email address.',
        ],
        'reset' => [
            'title' => 'New password',
            'subtitle' => 'Choose the password for your account :email.',
            'submit' => 'Save password',
            'invalid_title' => 'Link expired',
            'invalid_text' => 'This reset link is no longer valid: it has already been used, has expired or was replaced by a newer request.',
            'request_new' => 'Request a new link',
            'done' => 'Your password has been changed. Sign in with your new password.',
            'email_subject' => 'Reset your password · :site',
            'email_preheader' => 'Single-use link, valid for one hour.',
            'email_hello' => 'Hello :name,',
            'email_intro' => 'A password reset was requested for your :site account.',
            'email_button' => 'Choose a new password',
            'email_expiry' => 'This link is valid for :minutes minutes and can only be used once.',
            'email_ignore' => 'If you did not request this, ignore this message: your current password remains valid.',
            'email_link_fallback' => 'If the button does not work, copy this link into your browser:',
            'email_footer' => 'Automated message sent by :site. Request made from IP address :ip.',
        ],
        'change' => [
            'title' => 'Choose your password',
            'subtitle' => 'Your account uses a temporary password. Replace it to access the back office.',
            'current' => 'Current password',
            'new' => 'New password',
            'confirm' => 'Confirm new password',
            'hint' => 'At least :min characters. A short phrase is safer and easier to remember than a complicated word.',
            'submit' => 'Save',
            'done' => 'Your password has been changed.',
            'current_invalid' => 'The current password is incorrect.',
            'same_as_current' => 'The new password must be different from the current one.',
        ],
        'password' => [
            'too_short' => 'The password must be at least :min characters long.',
            'too_long' => 'The password cannot exceed 128 characters.',
            'too_weak' => 'This password is too easy to guess. Please choose another one.',
            'mismatch' => 'The two passwords do not match.',
        ],
        'account' => [
            'title' => 'My account',
            'subtitle' => 'Your sign-in details.',
            'identity' => 'Identity',
            'name' => 'Name',
            'role' => 'Role',
            'agency' => 'Agency',
            'last_login' => 'Last sign-in',
            'never' => 'First sign-in',
            'security' => 'Password',
            'security_help' => 'Changing your password signs you out on your other devices.',
            'profile_later' => 'Editing contact details will be available with user management.',
        ],
        'dashboard' => 'Dashboard',
    ],

    'errors' => [
        'eyebrow' => 'Error :code',
        'back_dashboard' => 'Back to dashboard',
        'reload' => 'Reload the page',
        401 => [
            'title' => 'Sign-in required',
            'text' => 'Sign in to access this page.',
        ],
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
