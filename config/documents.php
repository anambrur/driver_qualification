<?php

return [
    // Platform capability (05 §2): photos uploaded into folders become PDFs and the original is
    // kept (req 2.4). Needs gd and exif, and a worker on the default queue.
    'convert_images' => (bool) env('DOCUMENTS_CONVERT_IMAGES', true),
];
