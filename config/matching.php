<?php

return [
    'algorithm_version' => 'v1',
    'minimum_score' => (float) env('MATCHING_MINIMUM_SCORE', 60),
    'weights' => [
        'size' => 40,
        'location' => 25,
        'period' => 20,
        'volume' => 15,
    ],
];
