<?php

return [
    'btn'                  => 'Check settings',
    'checking'             => 'Checking…',
    'not_telegram'         => 'Checking is available for Telegram only.',
    'no_token'             => 'The sending bot is not configured.',
    'chat_not_found'       => 'The bot cannot see this chat. Add the bot to the group or channel as an administrator and bind the chat again.',
    'bot_not_member'       => 'The bot is not in this chat (removed or blocked). Add it again.',
    'bot_cannot_post_channel' => 'The bot is in the channel but cannot post. Give it the “Post messages” right.',
    'bot_restricted'       => 'The bot is not allowed to write in this chat. Lift the restriction or make it an administrator.',
    'general_topic'        => 'Topic #1 is the main (“General”) topic. Do not enter its number: leave the field empty — the announcement still goes to the main topic.',
    'ok'                   => 'All good: the bot is in the chat and can post.',
    'ok_forum_main'        => 'All good. The group has topics but no topic number is set — the announcement goes to the main (“General”) topic.',
    'ok_topic'             => 'All good: topic #:n found and the bot can post.',
    'not_forum'            => 'A topic number (:n) is set but this chat has no topics (a regular group or channel). Leave the field empty.',
    'topic_not_found'      => 'There is no topic #:n in this chat. Check the number: open the topic in Telegram, tap “Copy link” — the number after the last “/” (t.me/c/…/TOPIC) is the topic number.',
    'topic_unknown'        => 'The chat and permissions are fine, but we could not verify topic #:n. Publish a test announcement to be sure.',
    'unavailable'          => 'Telegram is not responding right now — could not check, try again later.',
    'fallback_title'  => 'Telegram topic unavailable — announcement went to the main topic',
    'fallback_body'   => 'The announcement “:title” was posted in “:channel” without a topic: there is no topic #:n in this chat (deleted, closed or a wrong number). Check the topic number with the “Check settings” button and fix it in the event or channel settings — the announcement will then move to the right topic.',
    'fallback_button' => 'Check channels',
];
