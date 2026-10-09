<?php

use Illuminate\Support\Facades\Process;

/*
| ACL-02: composer.lock pinned packages with published security advisories (49 in 13 packages on
| 2026-10-09, 13 of them high). Fixed by `composer update` within the existing constraints.
| Needs network access to the advisory database and is skipped offline. A newly published
| advisory fails this test even if nothing changed here; skip it with --exclude-group=audit.
*/

it('has no security advisories for the locked dependencies', function () {
    $result = Process::path(base_path())->timeout(120)
        ->run(['composer', 'audit', '--locked', '--format=json', '--no-interaction']);

    $report = json_decode($result->output(), true);

    if (! is_array($report)) {
        $this->markTestSkipped('composer audit did not return a report (offline?): '.$result->errorOutput());
    }

    $advisories = collect($report['advisories'] ?? [])
        ->flatMap(fn (array $list, string $package) => collect($list)
            ->map(fn (array $advisory) => sprintf('%s [%s] %s', $package, $advisory['severity'] ?? '?', $advisory['title'])))
        ->values()->all();

    expect($advisories)->toBe([]);
})->group('audit');
