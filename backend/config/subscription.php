<?php

return [
    /*
    | Trial length for every newly created organization (Asia/Manila calendar days).
    */
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 30),

    /*
    | Grace after trial/paid period ends — full access + urgent banner.
    */
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

    /*
    | Plan slug used for seat/feature limits while on trial (must exist in catalog).
    */
    'trial_plan' => env('SUBSCRIPTION_TRIAL_PLAN', 'standard'),

    /*
    | Timezone for all trial/grace/expiry date math.
    */
    'timezone' => 'Asia/Manila',
];
