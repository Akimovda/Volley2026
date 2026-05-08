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
    'pda_2_4' => 'player profile data: levels (indoor/beach), positions/zones, city, height, birth date (if filled)',
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

    // === pages/level_players.blade.php ===
    'lp_title'        => 'Player levels',
    'lp_description'  => 'Definitions from volleymsk.ru forum + our additions to the "Confident continuing amateur" level.',
    'lp_t_description' => 'Definitions from <span class="bold">volleymsk.ru</span> forum + our additions to the "Confident continuing amateur" level.',
    'lp_breadcrumb'   => 'Player levels',

    'lp_tab_classic'  => 'Indoor',
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

    'tf_title'         => 'Tournament formats',
    'tf_description'   => 'Volleyball tournament formats and schemes: round robin, single elimination, swiss and others.',
    'tf_t_description' => 'Volleyball tournament formats and schemes',
    'tf_breadcrumb'    => 'Tournament formats',
    'tf_h1'            => 'Tournament formats',
];
