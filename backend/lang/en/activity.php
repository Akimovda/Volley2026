<?php

return [
    'menu_item'            => '❤️ Heart Rate & Activity',
    'my_activity'          => '❤️ My Activity',
    'activity_sensors_settings' => 'Activity sensor settings',
    'page_title'           => 'Activity sensor settings',
    'page_subtitle'        => 'HR zones using the Karvonen method',
    'settings_heading'     => 'My Parameters',

    'resting_hr_label'       => 'Resting heart rate (bpm)',
    'resting_hr_placeholder' => 'e.g. 60',
    'resting_hr_hint'        => 'Measure in the morning right after waking up. Used to calculate zones.',

    'max_hr_label'           => 'Maximum heart rate (bpm)',
    'max_hr_placeholder'     => 'e.g. 190',
    'max_hr_age_hint'        => 'Optimal for your age: ~:bpm bpm (Tanaka formula)',

    'weight_label'           => 'Weight, kg',
    'weight_placeholder'     => 'e.g. 75',
    'weight_hint'            => 'Weight (kg) — needed to estimate calories burned.',

    'save_btn'               => 'Save',
    'saved'                  => 'Settings saved',

    'zones_heading'          => 'HR Zones',
    'zones_description'      => 'Calculated automatically from your parameters using the Karvonen method.',
    'zones_karvonen_note'    => 'HR_zone = HR_rest + % × (HR_max − HR_rest)',
    'zones_no_data_hint'     => 'Fill in your age and resting heart rate in settings to get personalised zones. Estimated values are shown for now.',

    'z1_name' => 'Recovery',
    'z2_name' => 'Aerobic Base',
    'z3_name' => 'Aerobic',
    'z4_name' => 'Anaerobic Threshold',
    'z5_name' => 'Maximum',

    // Workout recording
    'record_page_title'      => 'Record Workout',
    'record_btn'             => '🫀 Record Workout',
    'available_in_app_only'  => 'Heart rate recording is only available in the VolleyPlay app.',
    'connect_sensor'         => 'Connect Sensor',
    'disconnect'             => 'Disconnect',
    'start'                  => 'Start',
    'stop'                   => 'Stop',
    'connecting'             => 'Connecting…',
    'connected'              => 'Sensor connected',
    'reconnecting'           => 'Reconnecting…',
    'disconnected'           => 'Sensor disconnected',
    'no_occurrence'          => 'No event linked',
    'select_occurrence'      => 'Event',
    'live_bpm'               => 'bpm',
    'zone_label'             => 'Zone',
    'timer_label'            => 'Time',
    'session_summary'        => 'Workout Summary',
    'avg_hr'                 => '🫀 Average HR',
    'max_hr'                 => 'Maximum HR',
    'min_hr'                 => 'Minimum HR',
    'duration'               => '⏳ Duration',
    'load_score'             => '💪 Load score',
    'samples_count'          => 'Samples',
    'done_btn'               => 'Done',
    'error_ble_init'         => 'Failed to initialize Bluetooth. Check permissions.',
    'saving'                 => 'Saving…',
    'error_no_session'       => 'Session creation failed. Please try again.',
    'time_in_zones'          => 'Time in zones',
    'zones_below_z1'         => 'Heart rate did not reach training zones (below :bpm bpm)',

    // Health data consent
    'consent_title'          => 'Health Data',
    'consent_checkbox'       => 'I consent to processing health data (heart rate and derivatives) per the Privacy Policy',
    'consent_link'           => 'Privacy Policy',
    'consent_required'       => 'Consent to process health data is required',

    // Devices
    'my_devices'             => 'My Devices',
    'device_help_ble'        => '"Add Sensor" is for chest heart rate monitors and BLE sensors (Polar, Wahoo, Garmin, etc.) that broadcast via Bluetooth.',
    'device_help_watch'      => 'Apple Watch connects differently: tap "Record Workout" and start a session — your Watch will appear in the list automatically after the first recording.',
    'device_acc_none'        => 'This sensor transmits heart rate only. Jump counting and movement-based load are not available.',
    'device_acc_relative'    => 'Heart rate — accurate. Jumps — approximate: we count them and track relative dynamics, not exact centimetres.',
    'device_acc_better'      => 'Heart rate — accurate. Jumps — with higher precision (sensor worn at the body).',
    'connect_device'         => 'Add Sensor',
    'paired_devices'         => 'Paired Sensors',
    'remove_device'          => 'Remove',
    'set_primary'            => 'Set as primary',
    'is_primary'             => 'Primary',
    'no_devices'             => 'No paired sensors',
    'connect_in_settings_hint' => 'First connect a sensor in the "My Devices" section of your profile settings.',
    'record_training'        => 'Record Workout',
    'last_connected_at'      => 'Last connected',
    'device_added'           => 'Sensor added',
    'adding_device'          => 'Connecting sensor…',

    // Supported devices hints (3 tiers)
    'devices_help_title'         => 'Supported devices',

    // Tier 1: full stats
    'devices_full_stats'         => 'Full stats (heart rate + jumps + height + steps)',
    'devices_watch_full'         => 'Apple Watch',
    'devices_movesense_full'     => 'Movesense HR+, Polar H10, Polar Verity Sense — coming soon',

    // Tier 2: partial — import after workout
    'devices_partial_import'     => 'Heart rate + steps + calories (import after workout)',
    'devices_healthkit_import'   => 'Via Health app: Xiaomi Mi Band, Garmin, Samsung, Polar and others',
    'devices_hc_import'          => 'Via Health Connect: Xiaomi, Samsung, Garmin, Polar and others',

    // Tier 3: partial — heart rate only, real-time
    'devices_ble_only'           => 'Heart rate only (real-time)',
    'devices_ble_sensors'        => 'BLE sensors: Coospo HW9, H808S and similar',

    'devices_jumps_note'         => '* Jumps and height — Apple Watch and professional BLE motion sensors (Movesense, Polar H10)',

    // Calories
    'calories'               => '🔥 Calories',
    'calories_measured'      => ':n kcal (Apple Watch)',
    'calories_estimated'     => '≈:n kcal',
    'calories_no_data'       => '— add your weight',
    'calories_estimate'      => '≈ :value kcal',
    'weight_for_calories'    => 'Set your weight in settings to see calories',
    'set_weight_hint'        => 'Configure profile',

    // Jumps
    'jumps_count'            => '🏐 Jumps',
    'jumps_not_tracked'      => 'This sensor does not track jumps',
    'jump_trend_higher'      => ':delta cm above personal average',
    'jump_trend_lower'       => ':delta cm below personal average',
    'jump_first_session'     => 'first session — baseline set',

    // Reach
    'reach_classic'          => 'Standing reach, classic (cm)',
    'reach_beach'            => 'Standing reach, beach (cm)',
    'reach_hint'             => 'Standing reach — the height you can touch flat-footed with arm raised (measured against a wall). Classic: with shoes; beach: barefoot.',
    'hitting_reach'          => '≈ :cm cm from the floor',

    // Steps
    'steps'                  => 'Steps',
    'steps_label'            => '👟 Steps',

    // Activity dashboard
    'menu_history'           => '❤️ My Activity',
    'dashboard_title'        => '❤️ My Activity',
    'no_sessions'            => 'No recorded workouts yet.',
    'total_sessions'         => 'Total workouts',
    'last_load'              => 'Load (last)',
    'last_date'              => 'Date',
    'filter_all'             => 'All',
    'filter_classic'         => 'Classic',
    'filter_beach'           => 'Beach',
    'hr_curve'               => 'HR Curve',
    'hr_chart_x_axis'        => 'Time',
    'session_free_training'  => 'Free Training',
    'history_link'           => '❤️ My Activity →',
    'back_to_list'           => 'Back',
    'jump_avg_height'        => 'Avg height',
    'jump_max_height'        => 'Max height',
    'jump_chart_title'       => 'Jump height chart',
    'jump_chart_y_axis'      => 'Height, cm',
    'jump_chart_x_axis'      => 'Time',
    'jump_chart_tooltip'     => 'cm',

    // Session sync status (activity list/detail page)
    'sync_pending'      => 'Syncing',
    'sync_pending_hint' => 'Data from your device is still uploading. Refresh the page in a few minutes.',
    'sync_stale'        => 'No data received',
    'sync_stale_hint'   => 'Sync with your device didn\'t finish. Try opening the app on your watch again.',
    'sync_settling'      => 'Data still coming in',
    'sync_settling_hint' => 'The numbers are ready, but may change slightly over the next couple of minutes as the last bits of data arrive from your device.',

    // Push prompt
    'prompt_body'            => 'Workout in progress — record your activity?',

    // Watch recording auto-confirm (?auto=1)
    // Source selection (Watch vs BLE)
    'record_with_watch'       => '🫀 Record with Apple Watch',
    'choose_source'           => 'Recording source',
    'source_watch'            => '⌚ Apple Watch',
    'source_ble'              => '🫀 HR Monitor',

    'auto_confirm_title'      => 'Workout in progress — record your activity?',
    'record_now'              => 'Record Activity',
    'not_now'                 => 'Not now',
    'recording_started_watch' => 'Recording started — stop it on your watch when done',

    // Session recovery
    'recovery_title'   => 'Unfinished workout found',
    'recovery_body'    => 'A previous recording was interrupted. Upload the data to the server?',
    'recovery_upload'  => 'Upload',
    'recovery_discard' => 'Discard',

    // Connection lost
    'disconnected_permanent' => 'Sensor connection lost. Reconnection attempts exhausted.',
    'try_again'              => 'Try again',

    'import_from_health'          => 'Import from Health',
    'import_from_health_connect'  => 'Import from Health Connect',
    'import_loading'       => 'Loading workouts...',
    'import_done'          => 'Imported: :count workouts',
    'import_skipped'       => '(skipped duplicates: :count)',
    'import_error'         => 'Import error. Please try again.',
    'import_no_workouts'   => 'No new workouts found in the last 30 days.',
    'import_permissions'   => 'Please allow Health data access in settings.',
    'import_hc_not_installed' => 'Health Connect is not installed. '
        . 'Please install the Health Connect app from the Play Store.',
    'import_server_error'     => 'Server error. Please try again later.',
    'import_cancelled'        => 'Import cancelled.',

    // Activity feature announcement (activity:announce)
    'announce_title' => '❤️ Activity Recording — new feature',
    'announce_body'  => "VolleyPlay now supports activity recording: heart rate, load zones and jump tracking via smart watches and heart rate monitors.\n\nData is collected only with your explicit consent when connecting a sensor. It's voluntary — declining has no effect on other features.\n\nPrivacy details: https://volleyplay.club/personal_data_agreement#health-data",
    'announce_push'  => '❤️ New: heart rate, load & jump tracking during workouts. Connect a sensor in the app.',

    // Deleting a session
    'delete_btn'           => 'Delete session',
    'delete_confirm_title' => 'Delete this session?',
    'delete_confirm_text'  => 'The session data (heart rate, jumps) will be permanently deleted.',
    'delete_confirm_btn'   => 'Yes, delete',
    'delete_cancel_btn'    => 'Cancel',
    'session_deleted'      => 'Session deleted',

    // Ghost sessions (no data)
    'ghost_badge'          => 'No data',
    'show_ghosts_link'     => 'Show empty sessions (:n)',
    'hide_ghosts_link'     => 'Hide empty sessions',
];
