<?php

return [

    // Tabs and menu
    'tab_player' => 'Player',
    'tab_org'    => 'Organizer',

    'menu_p1'         => 'Sign up and login',
    'menu_p2'         => 'Linking accounts',
    'menu_p_app'      => 'Mobile app',
    'menu_p3'         => 'How to join an event',
    'menu_p_waitlist' => 'Waitlist',
    'menu_p_team'     => 'Tournaments and teams',
    'menu_p_booking'  => 'Court booking',
    'menu_p_activity' => 'Activity tracking',
    'menu_p4'         => 'Notifications',
    'menu_p5'         => 'Payment',
    'menu_p6'         => 'Cancelling a registration',
    'menu_p7'         => 'Profile and skill level',
    'menu_p_rating'   => 'Player rating',

    'menu_o1'         => 'How to become an organizer',
    'menu_o2'         => 'How to create an event',
    'menu_o3'         => 'Managing participants',
    'menu_o_waitlist' => 'Waitlist',
    'menu_o_tournament' => 'Tournaments',
    'menu_o_league'   => 'Leagues and seasons',
    'menu_o_venue'    => 'Managing a sports venue',
    'menu_o4'         => 'Payments and transactions',
    'menu_o6'         => 'Notifications to participants',
    'menu_o_crm'      => 'CRM and analytics',
    'menu_o7'         => 'Promoted events',

    // ===== PLAYER =====

    'p1_h'  => 'Sign up and login',
    'p1_p1' => 'On VolleyPlay.Club you don\'t need to come up with a password — you sign in through familiar services: <strong>Telegram</strong>, <strong>VKontakte</strong>, <strong>Yandex</strong>, <strong>Google</strong>, and for iPhone/iPad users — <strong>Apple</strong>. We don\'t get access to your messages or personal photos — only basic profile data (name, avatar).',
    'p1_p2' => 'On first login the service will ask for your name, city and skill level — this is needed to match you with suitable events.',
    'p1_p3' => '<strong>What happens if you log in with a different provider?</strong> If you previously signed in via Telegram and now try VK, a new account will be created. To avoid this, link all your accounts in profile settings.',

    'p2_h'  => 'Linking additional accounts',
    'p2_p1' => '<strong>Why link accounts?</strong> Linking lets you sign in through any of the providers and receive notifications in several channels at once — for example, both Telegram and VKontakte.',
    'p2_p2' => '<strong>How to link:</strong> open your <a href=":profile_url">Profile</a> → the "Accounts and linking" section → click the button for the provider you want and complete authorization.',
    'p2_p3' => '<strong>How to unlink:</strong> in the same section click "Unlink" next to the provider. Make sure at least one account stays linked — otherwise you won\'t be able to log in.',
    'p2_p4' => '<strong>Available providers:</strong> Telegram, VKontakte, Yandex, Google, Apple (iOS only).',

    'p_app_h'   => 'Mobile app',
    'p_app_p1'  => 'VolleyPlay.Club is available as a native smartphone app. Download it from your device\'s app store:',
    'p_app_li1' => '<strong>iPhone / iPad:</strong> App Store → "VolleyPlay"',
    'p_app_li2' => '<strong>Android:</strong> RuStore → "VolleyPlay"',
    'p_app_p2'  => 'The app gives you several advantages over the browser:',
    'p_app_li3' => '<strong>Push notifications</strong> — instant alerts about registrations, changes and reminders right on your phone screen, no messengers needed',
    'p_app_li4' => '<strong>Face ID / Touch ID login</strong> — after the first login you can authenticate with biometrics, no button taps needed',
    'p_app_li5' => '<strong>Quick access</strong> — home screen icon, works like a regular app',
    'p_app_p3'  => 'All website features are fully available in the app. You have a single account — data syncs automatically between the website and the app.',

    'p3_h'  => 'How to join an event',
    'p3_p1' => 'Go to the <a href=":events_url">events</a> page, pick a suitable one and click <strong>"Join"</strong>. For some events you\'ll need to choose a position (setter, outside hitter, etc.).',
    'p3_p2' => 'If all spots are taken, you can join the <strong>waitlist</strong>. When a spot opens up the system will register you automatically — a notification will arrive via your connected messenger or push.',
    'p3_p3' => 'Private events are only accessible via an invite link from the organizer.',

    'p_waitlist_h'  => 'Waitlist',
    'p_waitlist_p1' => 'If an event has no free spots, you can join the <strong>waitlist</strong>. As soon as someone cancels their registration, the system automatically adds you to the roster — no separate confirmation needed.',
    'p_waitlist_p2' => 'Waitlist priority: <strong>Premium players</strong> are processed first, then the queue order (by join date). The organizer can manually change the queue order.',
    'p_waitlist_p3' => 'If an event has a gender restriction (e.g. "women register first"), the system takes the gender window into account when picking the next participant.',
    'p_waitlist_p4' => 'You can leave the waitlist at any time — the "Leave the queue" button on the event page.',

    'p_team_h'   => 'Tournaments and teams',
    'p_team_p1'  => 'In tournaments, registration happens by teams (2×2, 4×4 and other formats). To take part:',
    'p_team_li1' => 'Create a team on the tournament page → give it a name and invite partners',
    'p_team_li2' => 'Or accept an invite from a team captain — a notification will arrive via messenger',
    'p_team_li3' => 'The team submits its application once it has enough players',
    'p_team_p2'  => 'You can save a permanent team lineup in your <a href=":profile_url">profile</a> → "My teams" — so you don\'t have to assemble it again for every tournament.',
    'p_team_p3'  => 'Some tournaments use <strong>individual registration</strong> — players sign up by position, and the organizer forms the teams.',

    // New section: court booking
    'p_booking_h'  => 'Court booking',
    'p_booking_p1' => 'If a venue is connected to the club module, its page shows a <strong>"🏐 Book a court"</strong> button — you can book a court directly, without tying it to someone else\'s event. Choose a direction (classic/beach), a court, and a date/time from the available slots.',
    'p_booking_p2' => '<strong>Payment and cancellation:</strong> if the club has online payment enabled, the booking is reserved for 30 minutes — pay by card (YooKassa) within that window, or the slot is released automatically. If the club accepts payment on site, the booking waits for the club\'s confirmation. Refund rules on cancellation (full refund before a deadline, or no refund) are set by the club itself — they are shown when you cancel a booking.',
    'p_booking_p3' => 'All your bookings — active ones and history — are in the <strong>"My bookings"</strong> section of the profile menu.',

    // New section: activity tracking
    'p_activity_h'   => 'Activity tracking',
    'p_activity_p1'  => 'You can record activity data during a game or training session — the <strong>"Activity"</strong> section in your profile. Several data sources are supported:',
    'p_activity_li1' => '<strong>Apple Watch</strong> — heart rate, jump count and jump height, steps, calories (calculated from heart rate, weight and age)',
    'p_activity_li2' => '<strong>BLE heart-rate sensor</strong> — any compatible chest heart-rate monitor connects directly via Bluetooth',
    'p_activity_li3' => '<strong>Import from Health / Health Connect</strong> — data from Xiaomi, Garmin, Polar, Samsung and other devices synced with Apple Health or Health Connect on Android',
    'p_activity_p2'  => 'After finishing a recording, the service shows a session summary and a jump-height trend compared to your recent games. The activity widget is available on the iOS and Android home screen.',

    'p4_h'  => 'Notifications',
    'p4_p1' => 'The service sends notifications to your connected channels: <strong>Telegram</strong>, <strong>VKontakte</strong>, <strong>MAX</strong>, and <strong>push notifications</strong> in the mobile app. You get notified about registrations, cancellations, event changes, match results and reminders before games.',
    'p4_p2' => 'To receive Telegram notifications, message our bot and tap "Start". For VKontakte — same via the VK bot. For push notifications — download the mobile app and allow notifications on first launch.',
    'p4_p3' => 'Notification settings (which types and which channels) are in your <a href=":profile_url">profile</a>.',

    'p5_h'  => 'Payment',
    'p5_p1' => 'Some events require prepayment. The payment method is set by the organizer — it can be YooKassa (online), T-Bank or Sberbank (via link).',
    'p5_p2' => 'After transferring the money, click <strong>"I paid"</strong> — the organizer will get a notification and confirm your registration. With YooKassa, confirmation happens automatically.',
    'p5_p3' => 'Some organizers use <strong>subscriptions</strong> — if you have an active subscription, the event fee is charged automatically when you register. Manage your subscriptions in the "My subscriptions" section of the profile.',

    'p6_h'  => 'Cancelling a registration',
    'p6_p1' => 'You can cancel a registration on the event page — click <strong>"Cancel registration"</strong>. The organizer can set a deadline until which self-cancellation is possible.',
    'p6_p2' => 'If the self-cancellation window has passed, contact the organizer directly.',
    'p6_p3' => 'After you cancel, the system automatically offers the spot to the first suitable participant on the waitlist.',

    'p7_h'  => 'Profile and skill level',
    'p7_p1' => 'In your profile, set your skill level for classic and beach volleyball — this helps organizers configure the admission filter for events.',
    'p7_p2' => 'Other players can rate your level — the final score is the average of all votes.',
    'p7_p3' => 'You can also add a photo, set your position, preferred zones (for beach), configure the privacy of your contact details, and view your rating history in the profile.',
    'p7_p4' => '<strong>Profile completeness</strong> affects access to registration: some events require a birth date, gender or skill level — the system will ask you to fill in the missing data when you try to register.',

    'p_rating_h'   => 'Player rating',
    'p_rating_p1'  => 'The service uses the <strong>OpenSkill rating system</strong> — an algorithm similar to the ones used in online games. It evaluates not just the number of wins, but also the strength of your opponents. The rating updates automatically after every completed match.',
    'p_rating_p2'  => '<strong>Conservative Rating (CR)</strong> — the public number shown in the table. Formula: <code>CR = μ − 3σ</code>, where μ is the expected skill level and σ is the uncertainty. The more matches played, the more accurate the rating.',
    'p_rating_li1' => 'A new player starts at CR ≈ 0 and grows as they play in tournaments',
    'p_rating_li2' => 'Ratings are calculated separately for beach and classic volleyball',
    'p_rating_li3' => 'Alongside OpenSkill, a classic <strong>Elo</strong> rating is also tracked — based on tournament results',
    'p_rating_p3'  => 'View the rating table on the <a href=":rating_url">rating page</a>. Your profile shows your rating history, regular partners and stats against opponents. A detailed explanation of the algorithm is on the <a href=":rating_info_url">rating info page</a>.',

    // ===== ORGANIZER =====

    'o1_h'  => 'How to become an organizer',
    'o1_p1' => 'By default, all users have the "Player" role. To be able to create events, submit a request in your <a href=":profile_url">profile</a> under "I want to become an organizer".',
    'o1_p2' => 'Briefly describe in the comment what you plan to organize: regular games, training sessions, tournaments, a league. An admin will review the request and activate the organizer role.',

    'o2_h'   => 'How to create an event',
    'o2_p1'  => 'Once you have the organizer role, a <strong>"Create event"</strong> button appears in the menu. The creation wizard has three steps:',
    'o2_li1' => '<strong>Step 1 — Type:</strong> direction (classic / beach), format (game, training, tournament, promoted), admission level, coach',
    'o2_li2' => '<strong>Step 2 — Time and place:</strong> date, time, venue, timezone, recurrence, registration settings',
    'o2_li3' => '<strong>Step 3 — Details:</strong> payment, privacy, description, cover image, bot assistant',
    'o2_p2'  => 'An event can be made <strong>recurring</strong> — for example, every Friday. The system automatically generates future dates up to 90 days ahead. Each occurrence of the series can be edited independently — override the venue, description, or registration settings for a specific date.',

    'o3_h'   => 'Managing participants',
    'o3_p1'  => 'On the event page, go to the <strong>"Participants"</strong> section. There you can:',
    'o3_li1' => 'Manually add a player by name or nickname',
    'o3_li2' => 'Change a participant\'s position',
    'o3_li3' => 'Confirm or cancel a registration',
    'o3_li4' => 'Export the participant list to PDF or a text file',
    'o3_li5' => 'Manage the waitlist — add players manually, change the queue order',
    'o3_p2'  => 'If you have a staff team, they can also manage participants for your events.',

    'o_waitlist_h'  => 'Waitlist',
    'o_waitlist_p1' => 'The waitlist on game events works <strong>automatically</strong>: as soon as a participant cancels, the system immediately finds the next suitable candidate in the queue and registers them without extra confirmation.',
    'o_waitlist_p2' => 'Priority: <strong>Premium players</strong> → then queue order (sort_order, then date). As the organizer, you can manually reorder the queue — the ↑↓ buttons in the "Waiting" section.',
    'o_waitlist_p3' => 'Gender restrictions are taken into account during auto-booking: if the event has an open "window" for the restricted gender, participants of the other gender don\'t block access to free spots.',
    'o_waitlist_p4' => 'You can also add a player to the waitlist manually from the participant management panel.',

    'o_tournament_h'   => 'Tournaments',
    'o_tournament_p1'  => 'For the "Tournament" format there is a dedicated management page — the <strong>"Tournament bracket"</strong>. The process:',
    'o_tournament_li1' => '<strong>Team sign-up</strong> — players register as teams (or you add them manually). Incomplete teams are shown separately ("looking for a partner")',
    'o_tournament_li2' => '<strong>Draw</strong> — assign teams to groups manually or click "Random draw". Available formats: groups + playoffs, single elimination, Swiss system, round robin, "King of the beach"',
    'o_tournament_li3' => '<strong>Matches</strong> — enter the score right on the page. The standings table, cross-table and bracket update automatically',
    'o_tournament_li4' => '<strong>Tiebreak</strong> — if scores are tied, the system creates a tiebreaker match or offers to resolve the tie by draw',
    'o_tournament_p2'  => 'A link to the public tournament page is available to participants — it shows groups, matches, the bracket and detailed stats (match heroes, team comparison, player tables) in real time.',

    'o_league_h'   => 'Leagues and seasons',
    'o_league_p1'  => 'For running regular competitions (leagues, championships), the platform uses a <strong>League → Season → Divisions → Rounds</strong> structure:',
    'o_league_li1' => '<strong>League</strong> — a long-term structure (e.g. "Amateur Beach League"). Can include a feeder league for promotion/relegation',
    'o_league_li2' => '<strong>Season</strong> — a time period (Spring, Summer, Fall). A season contains one or more divisions',
    'o_league_li3' => '<strong>Rounds</strong> — individual events (tournaments) that make up the season',
    'o_league_p2'  => '<strong>Promotion</strong> — at the end of a season, the system automatically promotes the best teams to the division above and relegates the bottom teams. Configurable per division: how many teams move up/down.',
    'o_league_p3'  => 'OpenSkill and Elo ratings are calculated separately per season, in addition to overall career stats.',

    // New section: managing a sports venue
    'o_venue_h'   => 'Managing a sports venue',
    'o_venue_p1'  => '<strong>How to get the role:</strong> the sports venue (club) manager role is assigned by a platform admin — contact support and specify which venue you plan to manage.',
    'o_venue_p2'  => 'Once the role is assigned, the location page (in the admin panel) gives you access to settings: <strong>directions</strong> (classic/beach), the list of <strong>courts</strong> (indoor/outdoor), and the <strong>working hours</strong> per day of the week.',
    'o_venue_p3'  => '<strong>Rental pricing</strong> is flexible: a base rate per court plus separate rules for prime time, weekdays and weekends — a more specific rule takes priority.',
    'o_venue_li1' => '<strong>Timeline</strong> — a visual view of court occupancy by day, including both platform events and manual bookings',
    'o_venue_li2' => '<strong>Bookings</strong> — the "Court bookings" panel: requests from players and organizers, confirm/reject, manually add a booking (including for guests without an account)',
    'o_venue_li3' => '<strong>Analytics</strong> — court occupancy and revenue (online/on-site) by period: month, quarter, half-year, year',
    'o_venue_p4'  => 'Renters receive automatic notifications and booking reminders in their connected channels — Telegram, VKontakte, MAX.',

    'o4_h'  => 'Payments and transactions',
    'o4_p1' => 'Set up your payment method in your <a href=":profile_url">profile</a> → "Payment settings". Available methods: YooKassa (automatic confirmation), T-Bank and Sberbank (manual confirmation).',
    'o4_p2' => 'The full payment history is available in the <strong>"Transactions"</strong> section. There you can also confirm a link payment or issue a refund.',
    'o4_p3' => '<strong>Virtual wallet</strong> — participants can top up their balance, and event fees will be charged automatically. You can manage the balance as an organizer in each participant\'s profile.',
    'o4_p4' => '<strong>Subscriptions</strong> — create a subscription template (number of visits / duration), and players can purchase it. The system automatically deducts a visit when they register for your events.',

    'o6_h'  => 'Notifications to participants',
    'o6_p1' => 'When an event is changed or cancelled, all participants automatically get a notification in their connected channels (Telegram, VKontakte, MAX, push in the app).',
    'o6_p2' => 'Automatic reminders also work — at a configurable time before the event starts.',
    'o6_p3' => '<strong>Organizer channels</strong> — you can link a Telegram group, channel, or VKontakte chat so event announcements are published there automatically. Set this up in event management → "Notification channels".',

    'o_crm_h'   => 'CRM and analytics',
    'o_crm_p1'  => 'Go to the <strong>"Dashboard"</strong> in the organizer menu. There you\'ll find analytics about your audience:',
    'o_crm_li1' => 'Overall participant stats — total, active, new in the last 30 days',
    'o_crm_li2' => 'Top active players with a period filter (7 / 30 / 90 days / all time)',
    'o_crm_li3' => 'Players at risk of churn — previously active but haven\'t registered in a while',
    'o_crm_li4' => 'Breakdown by gender, level and position',
    'o_crm_li5' => 'Frequently placed in reserve — potential candidates for a permanent spot',
    'o_crm_p2'  => '<strong>Tournament analytics</strong> is also available — stats on teams, matches and participant progress within your tournaments.',

    'o7_h'  => 'Promoted events',
    'o7_p1' => 'A promoted event is one without online registration through the service (e.g. a tournament with its own registration system). It\'s shown in the public catalog as an announcement.',
    'o7_p2' => 'Listing a promoted event is paid. After creating it you\'ll get a payment link. Once payment is confirmed, the event appears in the public list.',

    // ===== CONTACT =====
    'contact_h'  => 'Didn\'t find an answer?',
    'contact_p1' => 'If you still have questions, write to us. We try to respond within one business day.',
    'contact_telegram_btn' => 'Message us on Telegram',
    'contact_vk_btn'       => 'Message us on VKontakte',

];
