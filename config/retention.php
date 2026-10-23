<?php

/*
| How long a deactivated company's records are kept before `companies:purge` may delete them
| (08 §15). Defaults kept on 23 Oct 2026 until counsel confirms them [L].
*/

return [
    // DQ file: employment + 3 years (49 CFR 391.51). Employment ends at the latest when the company closes.
    'dq_file_years' => 3,

    // Vehicle maintenance: 1 year, and 6 months after the vehicle leaves the carrier (49 CFR 396.3)
    'maintenance_years' => 1,
    'maintenance_months_after_exit' => 6,

    // Financial records: payments [A]
    'financial_years' => 7,
];
