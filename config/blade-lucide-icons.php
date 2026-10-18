<?php

return [

    'prefix' => 'lucide',

    'fallback' => '',

    'class' => '',

    // Lucide SVGs ship without width/height; without these an icon that misses a size-* class
    // (or renders before the CSS loads) falls back to the browser's 300x150 default.
    'attributes' => [
        'width' => 24,
        'height' => 24,
    ],

];
