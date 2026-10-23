<?php

return [
    // Req 2.5: a ZIP above this many source bytes fails; export single drivers instead
    'max_bytes' => (int) env('EXPORTS_MAX_BYTES', 2 * 1024 ** 3),

    // A ready ZIP can be downloaded this long, then exports:prune deletes it (10 §9)
    'keep_days' => 7,

    // A queued or running export older than this is marked failed by exports:prune
    'stuck_after_minutes' => 120,
];
