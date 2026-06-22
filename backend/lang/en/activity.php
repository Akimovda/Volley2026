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

    // Health data consent
    'consent_title'          => 'Health Data',
    'consent_checkbox'       => 'I consent to processing health data (heart rate and derivatives) per the Privacy Policy',
    'consent_link'           => 'Privacy Policy',
    'consent_required'       => 'Consent to process health data is required',

    // Devices
    'my_devices'             => 'My Devices',
    'device_acc_none'        => 'This sensor transmits heart rate only. Jump counting and movement-based load are not available.',
    'device_acc_relative'    => 'Heart rate — accurate. Jumps — approximate: we count them and track relative dynamics, not exact centimetres.',
    'device_acc_better'      => 'Heart rate — accurate. Jumps — with higher precision (sensor worn at the body).',
    'connect_device'         => 'Add Sensor',
    'paired_devices'         => 'Paired Sensors',
    'remove_device'          => 'Remove',
    'no_devices'             => 'No paired sensors',
    'connect_in_settings_hint' => 'First connect a sensor in the "My Devices" section of your profile settings.',
    'record_training'        => 'Record Workout',
    'last_connected_at'      => 'Last connected',
    'device_added'           => 'Sensor added',
    'adding_device'          => 'Connecting sensor…',

    // Calories
    'calories'               => '🔥 Calories',
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
    'session_free_training'  => 'Free Training',
    'history_link'           => '❤️ My Activity →',
    'back_to_list'           => 'Back',
    'jump_avg_height'        => 'Avg height',
    'jump_max_height'        => 'Max height',

    // Push prompt
    'prompt_body'            => 'Workout in progress — record your activity?',

    // Watch recording auto-confirm (?auto=1)
    'auto_confirm_title'      => 'Workout in progress — record your activity?',
    'record_now'              => 'Record Activity',
    'not_now'                 => 'Not now',
    'recording_started_watch' => 'Recording started — stop it on your watch when done',
];
