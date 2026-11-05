<?php

return [
    // Laravel defaults
    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // /login page
    'login_title'         => 'Sign in to your account',
    'login_description'   => 'Sign in via Telegram, VK, Yandex, or with email',
    'login_h1'            => 'Sign in',
    'login_h2'            => 'Welcome back',
    'login_t_description' => 'Sign in to continue',
    'login_breadcrumb'    => 'Sign in',

    // Guest login popup (the "Join" button on an event card/page)
    'login_popup_title'    => 'Sign in to continue',
    'login_by_email'        => 'Sign in with email',
    'review_login_note'     => 'Password sign-in is for service accounts. If you signed up with Apple, VK, Yandex or Telegram, sign in the same way.',
    'review_login_back'     => 'Back to sign in',
    'review_login_failed'   => 'Incorrect email or password',
    'login_popup_subtitle' => 'After signing in you\'ll return to this event and can join.',
    'max_title'              => 'Sign in with MAX',
    'max_link_title'         => 'Link MAX',
    'max_description'        => 'Sign in to VolleyClub through the MAX messenger: the bot confirms the sign-in with a code.',
    'max_h2'                 => 'Confirm the sign-in in the MAX messenger',
    'max_step_open'          => 'Tap “Open MAX” — the VolleyClub bot chat will open.',
    'max_step_start'         => 'In the chat tap “Start”.',
    'max_step_code'          => 'Send the bot the 4-digit code shown below on this page.',
    'max_step_back'          => 'Come back here — you will be signed in automatically.',
    'max_open_btn'           => 'Open MAX',
    'max_copy_btn'           => 'Copy link',
    'max_copied'             => 'Copied ✅',
    'max_code_label'         => 'Your code',
    'max_status_pending'     => 'Waiting for you to open the bot and tap “Start”…',
    'max_status_awaiting_code' => 'The bot is waiting for the code — send it the 4 digits shown above.',
    'max_status_done'        => 'Done! Signing in…',
    'max_status_expired'     => 'Time is up. Please start again.',
    'max_status_denied'      => 'Too many wrong attempts. Please start again.',
    'max_status_error'       => 'Could not complete the sign-in. Please start again.',
    'max_hint'               => 'The code is valid for :min minutes and is shown only on this page — do not share it. If MAX did not open, copy the link and open it in MAX.',
    'max_restart_btn'        => 'Start again',
];
