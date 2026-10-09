<?php

use Intervention\Image\Drivers\Gd\Driver;

return [
    'driver' => Driver::class,
    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => false,
        'blendingColor' => 'ffffff',
        'strip' => true,
    ],
];
