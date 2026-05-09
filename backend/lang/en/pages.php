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
];
