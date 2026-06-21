<?php

return [
    'menu_item'            => '❤️ Heart Rate & Activity',
    'page_title'           => 'Heart Rate Settings',
    'page_subtitle'        => 'HR zones using the Karvonen method',
    'settings_heading'     => 'My Parameters',

    'resting_hr_label'       => 'Resting heart rate (bpm)',
    'resting_hr_placeholder' => 'e.g. 60',
    'resting_hr_hint'        => 'Measure in the morning right after waking up. Used to calculate zones.',

    'max_hr_label'           => 'Maximum heart rate (bpm)',
    'max_hr_placeholder'     => 'e.g. 190',
    'max_hr_age_hint'        => 'Optimal for your age: ~:bpm bpm (Tanaka formula)',

    'weight_label'           => 'Weight, kg (optional)',
    'weight_placeholder'     => 'e.g. 75',
    'weight_hint'            => 'Reserved for future calorie calculation.',

    'save_btn'               => 'Save',
    'saved'                  => 'Settings saved',

    'zones_heading'          => 'HR Zones',
    'zones_description'      => 'Calculated automatically from your parameters using the Karvonen method.',
    'zones_karvonen_note'    => 'HR_zone = HR_rest + % × (HR_max − HR_rest)',

    'z1_name' => 'Recovery',
    'z2_name' => 'Aerobic Base',
    'z3_name' => 'Aerobic',
    'z4_name' => 'Anaerobic Threshold',
    'z5_name' => 'Maximum',
];
