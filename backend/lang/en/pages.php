<?php

return [
    // === pages/personal_data_agreement.blade.php ===
    'pda_title'        => 'Personal Data Processing Consent',
    'pda_description'  => 'This consent applies when using the Volley service (registration, sign-in, profile, participation in events).',
    'pda_breadcrumb'   => 'Personal Data Agreement',
    'pda_warning'      => 'If you do not agree with the terms — please do not use the service and do not provide us with personal data.',

    'pda_h_1' => '1. Who processes the data',
    'pda_p_1' => 'The personal data operator is the Volley service (the "Operator"). Contact for inquiries: service administrator (via the form/contacts on the site).',

    'pda_h_2' => '2. What data may be processed',
    'pda_2_1' => 'external provider identifiers: Telegram ID, VK ID, Yandex ID',
    'pda_2_2' => 'public provider profile data: first/last name, username (if any), avatar (if provided)',
    'pda_2_3' => 'contact data: phone, email (incl. service email if the provider does not return one)',
    'pda_2_4' => 'player profile data: levels (classic/beach), positions/zones, city, height, birth date (if filled)',
    'pda_2_5' => 'technical data: cookies/session, IP, action logs for security',

    'pda_h_3' => '3. Processing purposes',
    'pda_3_1' => 'user registration and authentication',
    'pda_3_2' => 'maintaining a player profile and displaying it on the service',
    'pda_3_3' => 'event sign-up and participation management',
    'pda_3_4' => 'security (preventing abuse, action audit)',
    'pda_3_5' => 'feedback and notifications related to the service',

    'pda_h_4' => '4. Data actions',
    'pda_p_4' => 'The Operator may perform: collection, recording, systematization, storage, refinement, use, transfer (only as needed for authentication providers), anonymization, blocking, deletion and destruction of personal data.',

    'pda_h_5' => '5. Transfer to third parties',
    'pda_p_5' => 'Data may be transferred only as necessary for authentication and the service infrastructure (e.g. Telegram/VK/Yandex sign-in providers, hosting and storage services), and as required by law.',

    'pda_h_6' => '6. Retention period',
    'pda_p_6' => 'Data is stored for the duration of service use and/or until the processing purposes are fulfilled, or until consent is withdrawn, unless otherwise required by law.',

    'pda_h_7' => '7. Withdrawal of consent',
    'pda_p_7' => 'You may withdraw your consent for personal data processing by contacting the Operator. Withdrawal may make further use of the service impossible (e.g. sign-in and event participation).',

    'pda_h_8' => '8. Confirmation of consent',
    'pda_p_8' => 'By clicking "Sign in" via Telegram/VK/Yandex and/or filling in your profile in the Volley service, you confirm you have read the terms and consent to the processing of personal data.',

    'pda_h_9'   => '9. Health and Physical Activity Data',
    'pda_9_1'   => 'The Workout Recording feature (Activity section) collects the following data via wearable devices (heart rate monitors, smartwatches) voluntarily connected by the User via BLE: heart rate (BPM) in real time; derived metrics — HR zones, average, maximum and minimum heart rate, estimated load score, estimated calories; jump data — count and approximate height (calculated from the device\'s motion sensor); session metadata — date, time, duration, and link to an event.',
    'pda_9_2'   => 'The data listed above constitutes a special category of personal data — health data — under Art. 10 of Federal Law No. 152-FZ "On Personal Data". It does not constitute biometric personal data within the meaning of Art. 11 of the same law: the collected metrics are not used and do not enable identification of the User. The legal basis for processing is the User\'s explicit consent, expressed on a dedicated consent screen in the app before connecting a sensor for the first time.',
    'pda_9_3'   => 'Data is used exclusively to display the User\'s personal workout statistics and physical fitness trends; it is visible only to the User and is not shared with third parties. Collection begins only after explicit device connection and consent — no data is recorded without these actions. Declining health monitoring does not affect access to other platform features (event sign-up, profile, rating).',
    'pda_9_4'   => 'The User may withdraw consent at any time: disconnect the device in Profile → Devices within the app, or submit a request to the Operator via the feedback form on the site. After withdrawal, no new health data is collected. Previously recorded data is retained for up to 12 months from the date of collection, unless a different period is required by law or the User requests early deletion.',

    // === pages/level_players.blade.php ===
    'lp_title'        => 'Player levels',
    'lp_description'  => 'Definitions from volleymsk.ru forum + our additions to the "Confident continuing amateur" level.',
    'lp_t_description' => 'Definitions from <span class="bold">volleymsk.ru</span> forum + our additions to the "Confident continuing amateur" level.',
    'lp_breadcrumb'   => 'Player levels',

    'lp_tab_classic'  => 'Classic',
    'lp_tab_beach'    => 'Beach',
    'lp_tab_child'    => 'Teens',
    'lp_tab_old'      => 'Adults',

    'lp_col_level'    => 'Level',
    'lp_col_desc'     => 'Description',

    'lp_calc_h2'      => 'Player quality coefficient',
    'lp_calc_intro'   => 'On the event page you\'ll see the average level of registered players: <strong>"Player level: 4.25 of 7"</strong>. This coefficient is meant to help you estimate the dynamics of a game or training.',
    'lp_formula1_h'   => 'How it\'s calculated (men only)',
    'lp_formula1_p'   => 'The system sums the points and divides by the number of registrations.',
    'lp_formula1_eg'  => 'Example: 18 players: 14 × (4 pts), 3 × (5 pts), 1 × (6 pts)',
    'lp_formula2_h'   => 'When women participate (and there are fewer women than men)',
    'lp_formula2_p'   => 'The formula differs: women have a reducing coefficient (equal to the number of women).',
    'lp_formula2_eg'  => 'Example: 18 players: 10 × (3 pts), 5 × (4 pts), 3 women × (4 pts)',
    'lp_formula2_note' => 'If women are the majority — the formula is the same as the first example, no reduction.',
    'lp_signoff'      => 'Sincerely,',

    'lp_lvl_1' => '1 - Beginner',
    'lp_lvl_2' => '2 - Beginner +',
    'lp_lvl_3' => '3 - Mid −',
    'lp_lvl_4' => '4 - Mid',
    'lp_lvl_5' => '5 - Mid +',
    'lp_lvl_6' => '6 - Semi-pro (CMS)',
    'lp_lvl_7' => '7 - Pro (MS)',
    'lp_lvl_god'   => '"GOD" level',
    'lp_lvl_ban'   => 'BAN!',

    'lp_child_h_start'    => 'Beginner level',
    'lp_child_h_start_pl' => 'Beginner level +',
    'lp_child_h_mid'      => 'Mid level',
    'lp_child_points'     => '0&nbsp;points',

    'lp_child_classic_start_p'    => 'From scratch — students learn all skills from the beginning. In-depth training of all technical skills: overhead pass, bump, attack technique, proper run-up, hand placement etc. Theory and rotation.',
    'lp_child_classic_start_pl_p' => 'Already understand rotation, know where to stand, have basic technical skills + basic physical skills.',
    'lp_child_classic_mid_p'      => 'Players who know rotations, understand substitutions and play 4/2 scheme. Technically proficient, perform actions consistently (serve, attack, pass), good physical preparation.',

    'lp_child_beach_start_p'    => 'From scratch… (overhead pass/bump, hit technique, run-up, hand placement etc.), theory and rotation.',
    'lp_child_beach_start_pl_p' => 'Understanding of rotation, where to stand, basic skills at an early stage + basic physical fitness.',
    'lp_child_beach_mid_p'      => 'Knowledge of rotations, 4/2 scheme, technical consistency (serve/attack/pass), good physical preparation.',

    'lp_adult_classic_1' => 'Throwing the ball over the net for fun, with two hands or however. Some can serve overhead and attack above the cable. No concept of roles — everyone plays as best they can!',
    'lp_adult_classic_2' => 'Playing with all main elements: serve, reception, set, attack, block. Designated setters (usually two). Sometimes manage to "spike" or "block in slippers" etc. Concept of roles — only the setter. Setter\'s set technique is missing — sets as best they can.',
    'lp_adult_classic_3' => 'Stable serve, reception, set, attack, block. Active play on the front line and in defense. Designated setters (usually two). Knowledge of player rotation in each zone. Concept of roles — only the setter. Set technique exists but doesn\'t always work — there\'s room for growth.',
    'lp_adult_classic_4' => 'Stable attack, double block. Usually a single setter (5-1) and first-tempo players. About this level of mid- and leading-team players in 3–4 leagues. Roles are present. Setter has good technique but can\'t always play first tempo and outside hitters. Libero: 70% reception with normal pass to the setter.',
    'lp_adult_classic_5' => 'Tempo and combination play (high sets, "wave" sets, quick crosses). 5-1 system, can deliver the ball to any zone and support any combination. Around 2nd league level or first-rank/sport-school graduates. Libero: 80% reception with pass to setter.',
    'lp_adult_classic_6' => 'First-rank players and CMS, former pros, current pro beach players. 1st and Premier League teams.',
    'lp_adult_classic_7' => 'Player on a pro team — their job is to train and play for the club.',
    'lp_adult_classic_god' => '"God" — an over-confident average amateur, inadequately self-assessing their level.',

    'lp_adult_beach_1' => 'Player is just learning the elements: overhead/bump, hit, serve, footwork, basic positions. Game is not formed yet, elements are unstable. Goal — proper technique in simple conditions.',
    'lp_adult_beach_2' => 'Knows basic elements: serves consistently, knows jump-set and jump hit, sets to the attack zone, receives simple serves and soft hits. Goal — apply technique in difficult conditions (receiving hits/rebounds, accurate sets, stronger serve).',
    'lp_adult_beach_3' => 'Can attack/defend/set/receive but has accuracy and quality errors + lacks physical (jump, hit power, "reach" in defense, jump serve). Knows zones and rotation; understands partner signs but can\'t always cue the attack zone for a partner.',
    'lp_adult_beach_4' => 'Stable attack/defense, accurate sets and reception. Errors mostly come from a strong serve/attack from the opponent or risky serve/attack. Goal — tactics: defending hits/dinks, blocking around the block, "serve play".',
    'lp_adult_beach_5' => 'Good technique, focus on tactics: cross-court serves, hits past the block, block-out, dinks, blocking (if height allows), good defensive positioning. Often a role specialist or universal. 1st–2nd rank; in leagues — above 2nd.',
    'lp_adult_beach_6' => '1st rank or CMS. Plays in major tournaments, high level of technique and tactics, but not on a permanent contract with a pro club.',
    'lp_adult_beach_7' => 'Player on a pro team — their job is to train and play for the club.',

    'about_title'         => 'About — VolleyPlay.Club',
    'about_description'   => 'VolleyPlay.Club — a service for players, organizers, coaches and sports centers. Easy event sign-up, team management and notifications.',
    'about_t_description' => 'About VolleyPlay.Club',
    'about_breadcrumb'    => 'About',
    'about_h1'            => 'About',

    'help_title'         => 'Help — VolleyPlay.Club',
    'help_description'   => 'Frequently asked questions and how-to guides for VolleyPlay.Club.',
    'help_t_description' => 'FAQ and how-to guides',
    'help_breadcrumb'    => 'Help',
    'help_h1'            => 'Help',

    'rules_title'         => 'Service rules',
    'rules_description'   => 'Rules of use for the VolleyPlay.Club service.',
    'rules_t_description' => 'Service rules',
    'rules_breadcrumb'    => 'Rules',
    'rules_h1'            => 'Service rules',

    'ua_title'         => 'Terms of use',
    'ua_description'   => 'Terms of use for the VolleyPlay.Club service.',
    'ua_t_description' => 'Terms of use',
    'ua_breadcrumb'    => 'Terms of use',
    'ua_h1'            => 'Terms of use',

    'ua_last_updated'  => 'Last updated: April 29, 2026',

    'ua_general_h2' => 'General provisions',
    'ua_general_p1' => 'These Terms of Use (the “Agreement”) govern the relationship between <strong>Individual Entrepreneur Pirogova Valentina Evgenyevna</strong>, INN: 503406890699, OGRNIP 319508100190340 dated 20 August 2019, operating under the <strong>VolleyPlay.Club</strong> brand (the “Service”, “we”, “our”), and the user (“you”, “user”), arising from access to and use of the <strong>VolleyPlay.Club</strong> platform and related services.',
    'ua_general_p2' => 'The Service is a platform for organizing and managing volleyball events that provides tools for organizers and participants, as well as additional paid services (Premium subscription and extended functionality).',
    'ua_general_p3' => 'By using the Service, you confirm that you have read this Agreement, accept its terms and undertake to comply with them. If you do not agree with the terms — please stop using the Service.',
    'ua_general_p4' => 'You must be at least <strong>18 years old</strong> to use the Service. If you are a minor, you may use the Service only with the consent of a parent or legal guardian.',

    'ua_account_h2' => '1. User account',
    'ua_account_1'  => '<strong>1.1.</strong> Most features of the Service require an account. Registration is available via third-party platforms (Telegram, VK, Yandex ID). When using third-party platforms, you must comply with their terms of use.',
    'ua_account_2'  => '<strong>1.2.</strong> You are responsible for keeping your credentials safe and for all actions performed in your account. Do not share account access with third parties.',
    'ua_account_3'  => '<strong>1.3.</strong> The account is personal. Rights to the account and any related purchases cannot be transferred to another person.',
    'ua_account_4'  => '<strong>1.4.</strong> We may restrict, suspend or delete an account in case of a violation of this Agreement. We will notify you in advance of significant restrictions, except in cases of gross violations.',

    'ua_usage_h2'      => '2. Use of the service',
    'ua_usage_1'       => '<strong>2.1.</strong> Subject to your compliance with this Agreement, we grant you a limited, non-exclusive, non-transferable license to use the Service for personal, non-commercial purposes.',
    'ua_usage_2_intro' => '<strong>2.2.</strong> You may use the Service to:',
    'ua_usage_2_li1'   => 'find and sign up for volleyball events;',
    'ua_usage_2_li2'   => 'create and manage events (for organizers);',
    'ua_usage_2_li3'   => 'purchase a Premium subscription and additional services;',
    'ua_usage_2_li4'   => 'interact with other platform participants.',
    'ua_usage_3'       => '<strong>2.3.</strong> We may at any time change the functionality of the Service, add or remove features, and carry out maintenance work. Where possible, we will notify users of changes in advance.',

    'ua_restrictions_h2'    => '3. Use restrictions',
    'ua_restrictions_intro' => '<strong>3.1.</strong> When using the Service, the following is prohibited:',
    'ua_restrictions_li1'   => 'using the Service for purposes not provided for by this Agreement or contrary to the laws of the Russian Federation;',
    'ua_restrictions_li2'   => 'copying, reproducing, selling or otherwise commercially exploiting the Service without our written consent;',
    'ua_restrictions_li3'   => 'reverse engineering, decompiling or disassembling the source code of the Service;',
    'ua_restrictions_li4'   => 'creating automated tools (bots, scripts) to interact with the Service without our permission;',
    'ua_restrictions_li5'   => 'removing or altering copyright and trademark notices;',
    'ua_restrictions_li6'   => 'taking actions that violate the rights of other users or third parties.',

    'ua_ip_h2' => '4. Intellectual property',
    'ua_ip_1'  => '<strong>4.1.</strong> All rights to the Service, including design, code, logos, texts, graphics and other materials, belong to Individual Entrepreneur Pirogova Valentina Evgenyevna or are used on lawful grounds. This Agreement does not transfer to you ownership rights to the Service.',
    'ua_ip_2'  => '<strong>4.2.</strong> User-generated content (profile photos, descriptions, comments) remains your property. By posting content on the platform, you grant us the right to use it for the purpose of operating the Service.',
    'ua_ip_3'  => '<strong>4.3.</strong> You warrant that the content you post does not infringe third-party rights and is not contrary to applicable law. We may remove content that violates this requirement without prior notice.',

    'ua_paid_h2'      => '5. Paid services and Premium subscription',
    'ua_paid_5_1_t'   => '<strong>5.1. Types of paid services</strong>',
    'ua_paid_5_1_li1' => 'Premium subscription for users (extended profile, special features, priority support);',
    'ua_paid_5_1_li2' => 'extended tools for event organizers;',
    'ua_paid_5_1_li3' => 'other additional services described on the relevant pages of the Service.',
    'ua_paid_5_2_t'   => '<strong>5.2. Premium subscription</strong>',
    'ua_paid_5_2_1'   => '<strong>5.2.1.</strong> The Premium subscription is provided on a paid basis for a specific period (month, year and other options).',
    'ua_paid_5_2_2'   => '<strong>5.2.2.</strong> By purchasing a subscription, you gain access to the extended functionality for the entire paid period. After it expires, access to Premium features ends unless the subscription is renewed.',
    'ua_paid_5_2_3'   => '<strong>5.2.3.</strong> You may cancel automatic renewal of the subscription at any time. The already-paid period of use is preserved.',
    'ua_paid_5_2_4'   => '<strong>5.2.4.</strong> Rights to the Premium subscription are personal and cannot be transferred to another user.',
    'ua_paid_5_3_t'   => '<strong>5.3. Pricing and payment</strong>',
    'ua_paid_5_3_1'   => '<strong>5.3.1.</strong> Current prices for the Service are listed on the corresponding pages of the platform. We may change prices with prior notice to users.',
    'ua_paid_5_3_2'   => '<strong>5.3.2.</strong> Payment is processed via third-party payment systems (YooKassa and others). By making a payment, you accept the terms of the relevant payment service.',
    'ua_paid_5_3_3'   => '<strong>5.3.3.</strong> When making a payment, you must provide accurate and up-to-date payment details.',

    'ua_refund_h2'    => '6. Refunds',
    'ua_refund_1'     => '<strong>6.1.</strong> All purchases are final. Refunds are issued in the following cases:',
    'ua_refund_1_li1' => 'a technical failure that made it impossible to provide the paid service;',
    'ua_refund_1_li2' => 'a duplicate charge;',
    'ua_refund_1_li3' => 'other cases provided for by Russian consumer-protection law.',
    'ua_refund_2'     => '<strong>6.2.</strong> To request a refund, contact us at <a href="mailto:akimovda@inbox.ru">akimovda@inbox.ru</a> within 14 days of payment. Attach payment confirmation and a description of the issue.',
    'ua_refund_3'     => '<strong>6.3.</strong> A refund is processed within 10 business days after the request is confirmed as valid. Funds are returned via the same method that was used for payment.',
    'ua_refund_4'     => '<strong>6.4.</strong> A partial refund for the unused subscription period is issued on a pro-rata basis, provided that the request is submitted within 14 days of payment. No refund is provided if the Premium functionality has been actively used.',

    'ua_conduct_h2'    => '7. Code of conduct',
    'ua_conduct_1'     => '<strong>7.1.</strong> When using the Service, you undertake to comply with generally accepted standards of conduct and the laws of the Russian Federation.',
    'ua_conduct_2'     => '<strong>7.2.</strong> The following is prohibited on the platform:',
    'ua_conduct_2_li1' => 'posting offensive, discriminatory, threatening or unlawful content;',
    'ua_conduct_2_li2' => 'spam, sending advertisements without our consent, trolling;',
    'ua_conduct_2_li3' => 'posting personal data of other users without their consent;',
    'ua_conduct_2_li4' => 'intentional disruption of the Service;',
    'ua_conduct_2_li5' => 'fraudulent actions when registering for events or making payments.',
    'ua_conduct_3'     => '<strong>7.3.</strong> We may take action against violators, including warning, temporary restriction or permanent blocking of the account.',

    'ua_liability_h2' => '8. Liability and warranties',
    'ua_liability_1'  => '<strong>8.1.</strong> The Service is provided “as is”. We make reasonable efforts to ensure smooth operation of the platform but do not guarantee the absence of failures or errors.',
    'ua_liability_2'  => '<strong>8.2.</strong> To the maximum extent permitted by Russian law, our liability for any losses arising in connection with the use of the Service is limited to the amount paid by you over the last 6 months.',
    'ua_liability_3'  => '<strong>8.3.</strong> We are not responsible for the actions of third parties (other users, event organizers, payment systems), nor for the content of events held on the platform.',
    'ua_liability_4'  => '<strong>8.4.</strong> This Agreement does not limit your rights as a consumer provided for by Russian consumer-protection law.',

    'ua_changes_h2' => '9. Changes to the agreement',
    'ua_changes_1'  => '<strong>9.1.</strong> We may amend this Agreement at any time. We will notify users of changes by publishing a new version on the website and/or by sending a notification.',
    'ua_changes_2'  => '<strong>9.2.</strong> Changes take effect 30 days after publication.',
    'ua_changes_3'  => '<strong>9.3.</strong> Continued use of the Service after the changes take effect constitutes your acceptance of the new version of the Agreement.',

    'ua_termination_h2' => '10. Termination',
    'ua_termination_1'  => '<strong>10.1.</strong> You may stop using the Service at any time by submitting an account-deletion request through your profile settings or by email.',
    'ua_termination_2'  => '<strong>10.2.</strong> When the account is deleted, all related data is irrevocably lost. Refunds for the unused period of a Premium subscription are issued in accordance with section 6.',
    'ua_termination_3'  => '<strong>10.3.</strong> We may restrict or terminate access to the Service in case of a violation of this Agreement. If the Service is discontinued as a whole, we will notify users at least 30 days in advance.',

    'ua_law_h2' => '11. Governing law and disputes',
    'ua_law_1'  => '<strong>11.1.</strong> This Agreement is governed by the laws of the Russian Federation.',
    'ua_law_2'  => '<strong>11.2.</strong> All disputes are resolved through negotiation. If pre-trial settlement is impossible, the dispute is referred to court in accordance with Russian law.',
    'ua_law_3'  => '<strong>11.3.</strong> Claims should be sent to <a href="mailto:akimovda@inbox.ru">akimovda@inbox.ru</a>. Review period — 30 days.',

    'ua_privacy_h2' => '12. Data privacy',
    'ua_privacy_1'  => '<strong>12.1.</strong> The collection, storage and processing of personal data is governed by the <a href="/personal_data_agreement">Privacy Policy</a>.',
    'ua_privacy_2'  => '<strong>12.2.</strong> By using the Service, you consent to the processing of your personal data in accordance with the Privacy Policy.',

    'ua_contacts_h2'    => '13. Contact information and details',
    'ua_contacts_block' => '<strong>Individual Entrepreneur Pirogova Valentina Evgenyevna</strong><br>
OGRNIP: 319508100190340 dated 20 August 2019<br>
INN: 503406890699<br>
Address: Moscow Region, Orekhovo-Zuevo, Lenin Street 49, apt./office 112<br>
Account number: 40802810202360001947<br>
Currency: RUR<br>
Bank: JSC “Alfa-Bank”<br>
BIC: 044525593<br>
Correspondent account: 30101810200000000593<br>
Email: <a href="mailto:akimovda@inbox.ru">akimovda@inbox.ru</a><br>
Website: <a href="https://volleyplay.club">volleyplay.club</a>',

    'ua_footer' => 'By using the Service, you confirm that you have read, understood and accepted the terms of this Agreement.',

    'tf_title'         => 'Tournament formats',
    'tf_description'   => 'Volleyball tournament formats and schemes: round robin, single elimination, swiss and others.',
    'tf_t_description' => 'Volleyball tournament formats and schemes',
    'tf_breadcrumb'    => 'Tournament formats',
    'tf_h1'            => 'Tournament formats',

    // ── /about (all page texts; structure: resources/views/pages/about.blade.php) ──
    'about' => [
        'hero_text' => 'A complete ecosystem for the volleyball community — from casual games to professional leagues. Event sign-up, tournaments, ratings, volleyball schools, court booking and a CRM for organizers.',
        'btn_find'        => 'Find an event',
        'btn_find_arrow'  => 'Find an event →',
        'btn_rating'      => 'Player rating',
        'btn_teams'       => 'Pairs and teams',
        'btn_rating_info' => 'How the rating works',
        'btn_become_org'  => 'Become an organizer',
        'btn_apply_org'   => 'Apply to become an organizer',
        'btn_org_dash'    => 'Organizer dashboard',
        'btn_activity'    => 'My workouts',
        'btn_leagues'     => 'All leagues',
        'btn_locations'   => 'Find a venue',
        'btn_schools'     => 'School catalog',
        'btn_premium'     => 'More about Premium',
        'btn_pro'         => 'Organizer Pro plans',
        'btn_wl'          => 'Discuss on Telegram',
        'btn_ios'         => 'App Store',
        'btn_android'     => 'RuStore',
        'btn_apk'         => 'Download for Android (APK)',

        'why_player_title' => 'Why players love it:',
        'why_player_text'  => 'Stop searching for a game in chats and calls — open the app and sign up in a minute.',
        'why_player_items' => [
            'Find a game at your level right now — filter by city, format and rating',
            'Your progress is never lost — a fair rating after every match, calculated automatically (OpenSkill)',
            "You won't miss a spot — the waitlist grabs it for you, even when you're not watching",
        ],
        'why_org_title' => 'Why organizers love it:',
        'why_org_text'  => 'Less admin in messengers — more time for the game itself.',
        'why_org_items' => [
            'Sign-up, waitlist and payments in one place, no manual lists',
            'Payment at registration — automatically via YooKassa or by T-Bank / Sber link',
            'Tournaments and leagues without spreadsheets — brackets, ratings and division promotion are calculated automatically',
        ],

        'audience_title' => 'Who the service is for',
        'audiences' => [
            ['🙋', 'Players', 'Find games near home, sign up in one click, track your OpenSkill rating and partner history.'],
            ['📋', 'Organizers', 'Create events, manage participants and payments. CRM dashboard, waitlist, tournaments, leagues and games with statistics.'],
            ['🏫', 'Volleyball schools', 'Your own school page, training schedule with online sign-up, subscriptions and group management.'],
            ['🏅', 'Coaches', 'A public coach profile with specialization and experience, ratings from training participants, training groups and the event catalog.'],
            ['🏆', 'Leagues and federations', 'Run seasons with divisions, automatic promotion, cross tables and player rating tracking.'],
            ['🏟️', 'Sports centers', 'Accept direct court bookings from players with online payment, publish your court schedule and track occupancy and revenue on one platform.'],
        ],

        'sections' => [
            'players' => [
                'title' => '🙋 Features for players',
                'blocks' => [[
                    'left' => [
                        '🔍 Search events by city, level and game format',
                        '✅ One-click event sign-up',
                        '⏳ Waitlist — automatic sign-up when a spot frees up',
                        '🔔 Notifications in Telegram, VKontakte and MAX',
                        '🏙 Notifications about new events in your city (enabled in your profile)',
                        '📍 Venue map with address and directions',
                        '🌤 Weather forecast for outdoor events (up to 5 days ahead)',
                        '🤝 Invite friends to an event',
                    ],
                    'right' => [
                        '📈 A fair skill rating — calculated automatically after every match (OpenSkill)',
                        '🎯 Profile with positions, level, tournament and partner history',
                        '🤜 Save your favorite pairs and teams',
                        '📊 Personal match statistics at games with statistics',
                        '🔒 Private events — by invitation link only',
                        '💳 Online payment via YooKassa, T-Bank or Sber',
                        '⭐ Premium subscription — see below',
                        '📲 Mobile app for iOS and Android',
                    ],
                ]],
            ],
            'premium' => [
                'title' => '⭐ Premium for players',
                'intro' => 'A subscription for those who play a lot and do not want to miss spots. 7-day free trial.',
                'blocks' => [[
                    'left' => [
                        '⏳ Waitlist priority — you get a freed-up spot first',
                        '🤖 Auto sign-up: up to 5 event series — the system signs you up as soon as registration opens (confirm your attendance within 12 hours)',
                    ],
                    'right' => [
                        '👀 Follow players — find out when they sign up for games',
                        '🔔 Personal notifications about new games at your level in your city and a weekly digest',
                    ],
                ]],
            ],
            'rating' => [
                'title' => '📈 OpenSkill rating system',
                'intro' => 'VolleyPlay.Club uses three independent ratings to objectively assess every player.',
                'cards' => [
                    ['⚡ OpenSkill — a fair rating', "Calculated automatically after every match, accounting not only for wins but also for the strength of opponents. For the technically minded: a Bayesian μ/σ algorithm; the public score is the conservative CR\u{00A0}=\u{00A0}μ\u{00A0}−\u{00A0}3σ, which you can trust."],
                    ['🎯 Elo rating', 'The classic rating based on completed tournaments. Calculated separately per season and for the whole career — handy for tracking progress.'],
                    ['📊 WinRate', 'Win percentage in matches, tournaments and series. Shows consistency of results and complements OpenSkill well.'],
                ],
                'blocks' => [[
                    'left' => [
                        '📉 Rating chart for recent matches in the player profile',
                        '🤜 Pair statistics: pairs for beach, permanent teams with rosters for indoor',
                        '⚔️ Head-to-head statistics against opponents',
                    ],
                    'right' => [
                        '🏄 Separate ratings for beach and indoor volleyball',
                        '📅 Season rating — you start fresh every season',
                        '🔝 Peak rating — history of your best result',
                    ],
                ]],
            ],
            'activity' => [
                'title' => '❤️ Workout tracker',
                'intro' => 'Connect a BLE heart rate sensor in the mobile app and watch your load right during the game.',
                'blocks' => [[
                    'left' => [
                        '❤️ Real-time heart rate from a BLE sensor (chest strap, watch)',
                        '🔥 Calories burned and time in heart rate zones per workout',
                        '📈 Average/maximum heart rate and overall load (load score)',
                    ],
                    'right' => [
                        '🦘 Jump counter and height — for compatible devices',
                        '📊 Jump height trend compared with previous workouts',
                        '📱 Available in the iOS and Android mobile app',
                    ],
                ]],
            ],
            'tournaments' => [
                'title' => '🏆 Tournaments and competitions',
                'intro' => 'A complete tournament system: from the draw to the final protocol.',
                'blocks' => [
                    [
                        'left_title'  => 'Formats for indoor and beach',
                        'left' => [
                            '🔁 Round Robin',
                            '🏟️ Group stage + playoffs, including final groups by level',
                            '❌ Single Elimination',
                            '🔄 Double Elimination',
                            '🇨🇭 Swiss system',
                        ],
                        'right_title' => 'Beach only',
                        'right' => [
                            '👑 King of the Court',
                            '🏖️ Beach King — an individual tournament with rotating partners',
                        ],
                    ],
                    [
                        'left' => [
                            '📋 Cross tables and live brackets',
                            '🤝 Team registration — captain + members',
                            '👤 Individual tournament sign-up (distribution into teams)',
                            '⚖️ Tiebreak system with additional criteria',
                        ],
                        'right' => [
                            '📺 TV mode — show the bracket on a screen',
                            '📄 Protocol export to PDF',
                            '🔀 Draw and team rearrangement by the organizer',
                            '📊 Ratings update automatically after every match',
                        ],
                    ],
                ],
            ],
            'stats_game' => [
                'title' => '📊 Game with statistics',
                'intro' => 'A regular game with matches, scores and statistics — no tournament bracket. The organizer turns it on with a checkbox when creating the event.',
                'blocks' => [[
                    'left' => [
                        '🔄 Line-ups change during the evening — teams are formed for each match from the players who signed up',
                        '🏐 Score by sets or point by point, statistics for every player',
                    ],
                    'right' => [
                        '🏅 Public results page: podium, player table, top scorers',
                        '📅 Series results for a period and a "Rated event" flag — matches count toward the overall rating',
                    ],
                ]],
            ],
            'leagues' => [
                'title' => '🏅 Leagues and seasons',
                'intro' => 'A long-term competition system with divisions, promotion and accumulated statistics.',
                'blocks' => [[
                    'left' => [
                        '🏢 Hierarchy: League → Season → Divisions → Rounds',
                        '⬆️ Automatic promotion and relegation at the end of the season',
                        '📋 Division roster with movement history',
                        '🔄 In-season player substitutions with double confirmation',
                    ],
                    'right' => [
                        '📊 Cumulative table for the whole season',
                        '🗓️ Rounds linked to specific divisions',
                        '👥 League reserve — a queue of teams for a division spot',
                        '🌐 Public page for the league and each season',
                    ],
                ]],
            ],
            'club' => [
                'title' => '🏟️ Court booking for clubs',
                'intro' => 'Tools for venue owners and sports centers: hall occupancy and bookings in one window.',
                'blocks' => [[
                    'left' => [
                        '🗓️ Court occupancy timeline by direction and hall',
                        '🏐 Players book a court directly on the site, no calls to the administrator',
                        '💳 Online booking payment via YooKassa',
                        '✅ Booking confirmation or rejection by the venue owner',
                    ],
                    'right' => [
                        '📊 Occupancy and revenue analytics by court and direction',
                        '🔔 Notifications about new bookings, payments and reminders',
                        '↩️ Flexible cancellation and refund policy',
                        '🏆 Automatic assignment of tournament matches to specific courts',
                    ],
                ]],
            ],
            'org' => [
                'title' => '📋 CRM and tools for organizers',
                'intro' => 'A full toolkit for managing events, your team and finances.',
                'blocks' => [[
                    'left_title' => 'Event management',
                    'left' => [
                        '📅 One-off events and recurring series',
                        '👥 Add/move participants, forced sign-up',
                        '⏳ Waitlist with manual ordering',
                        '🔔 Push notifications to participants about changes',
                        '📢 Announcements in Telegram channels, VKontakte groups and MAX',
                        '📄 Participant list export to PDF and TXT',
                    ],
                    'right_title' => 'Analytics and finance',
                    'right' => [
                        '📊 Organizer dashboard: activity, sign-ups, revenue, event occupancy',
                        '🧑‍🤝‍🧑 Player and tournament analytics — in Organizer Pro',
                        '💰 Payment acceptance: YooKassa, T-Bank, Sberbank',
                        "👛 Player's virtual wallet with the organizer",
                        '🎫 Subscriptions and discount coupons',
                        '👨‍💼 Assistant team (staff) with an action log',
                    ],
                ]],
            ],
            'pro' => [
                'title' => '⭐ Organizer Pro',
                'intro' => 'An extended plan for organizers who are building their own community. 7-day free trial.',
                'blocks' => [[
                    'left' => [
                        '🤖 Your own bot: announcements from your personal bot in Telegram and MAX',
                        '🌐 Website widget: a list of your events via iFrame or a JS script',
                    ],
                    'right' => [
                        '📊 Player analytics (audience, top players, churn, CSV/PDF export) and tournament analytics',
                        '🛠 Priority support',
                    ],
                ]],
            ],
            'white_label' => [
                'title' => '📱 Your own app — White Label',
                'intro' => 'A club, school or league gets a separate mobile app under its own name. Connected separately from the subscription; the price depends on the amount of setup — on request.',
                'blocks' => [[
                    'left' => [
                        '🎨 Your brand: name, icon and splash screen, logo and colors in light and dark themes',
                        '🏐 Only your games: the app feed shows your organization’s events',
                    ],
                    'right' => [
                        '🧭 Your own menu: hide unneeded sections and add your own links',
                        '📲 In the stores: App Store and RuStore, push notifications',
                    ],
                ]],
            ],
            'school' => [
                'title' => '🏫 Volleyball schools',
                'intro' => 'A separate module for coaches and heads of volleyball schools.',
                'blocks' => [[
                    'left' => [
                        '📄 School page with description, photos and contacts',
                        '📅 Training group schedule with online sign-up',
                        '🎓 Groups by level: beginners, advanced, mixed',
                        '💳 Subscriptions — sell training packages online',
                    ],
                    'right' => [
                        '🔔 Automatic training reminders for students',
                        '📍 Linked to a venue/hall with a map',
                        '🏅 Coach profile with ratings from training participants',
                        '📢 Promotion in the common event and school catalogs',
                    ],
                ]],
            ],
            'app' => [
                'title' => '📱 Mobile app',
                'blocks' => [[
                    'left' => [
                        '🍎 iOS — available in the App Store',
                        '🤳 Face ID / Touch ID for quick sign-in (iOS)',
                        '🔔 Push notifications about sign-ups, reminders and tournaments',
                        '🌐 All site features in a convenient mobile form',
                    ],
                    'right' => [
                        '🤖 Android — available in RuStore',
                        '🌙 Dark theme — follows the system setting automatically',
                        '🔗 Universal Links — links open right in the app',
                        '🔑 Sign in with Telegram, VKontakte, Yandex, Google or Apple ID (the set of methods depends on the device)',
                    ],
                ]],
            ],
        ],

        'bots_title' => '🤖 Bots and notifications',
        'bots' => [
            ['tg', 'Telegram', 'Notifications about sign-ups, cancellations, 2-hour reminders and changes. Announcements in the organizer’s channels and groups.'],
            ['vk', 'VKontakte', 'Notifications in VKontakte messages. Announcements published in chats and communities.'],
            ['max', 'MAX', 'A bot in the MAX messenger — notifications and announcements for the audience on the platform.'],
        ],

        'cities_title' => '📍 Launch cities',
        'cities_text'  => 'We operate and are launching in these cities. The list keeps growing — want to add your city?',
        'cities_contact' => 'Contact us',
        'cities' => [
            ['Moscow', '🏙️'], ['Novosibirsk', '❄️'], ['Saint Petersburg', '🌊'], ['Sestroretsk', '🌊'],
            ['Voronezh', '🌿'], ['Lipetsk', '🌿'], ['Saratov', '🌾'], ['Sochi', '☀️'], ['Sirius', '☀️'],
        ],

        'start_title' => '🚀 How to start',
        'steps' => [
            ['Sign up', 'Sign in with Telegram, VKontakte, Yandex, Google or Apple ID — no passwords or extra data.'],
            ['Fill in your profile', 'Add your position, level and city — the system will pick suitable events and start calculating your rating.'],
            ['Find an event', 'Choose a city, format and level — sign up for the nearest game, training or tournament.'],
            ['Play and grow', 'After matches your rating updates automatically. Join leagues and tournaments, track your progress.'],
        ],
    ],
];
