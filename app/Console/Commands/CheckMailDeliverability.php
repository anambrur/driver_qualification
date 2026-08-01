<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckMailDeliverability extends Command
{
    protected $signature = 'mail:check-deliverability
                            {--domain= : Domain to check (defaults to MAIL_FROM_ADDRESS host)}
                            {--selector=google : Primary DKIM selector to require}';

    protected $description = 'Verify SPF, DKIM, and DMARC DNS records for outbound mail authentication';

    /** @var list<string> */
    private const DKIM_SELECTORS = [
        'google',
        'default',
        'selector1',
        'selector2',
        'k1',
        's1',
        's2',
        'gm1',
    ];

    public function handle(): int
    {
        $domain = $this->resolveDomain();

        if ($domain === null) {
            $this->error('Could not determine domain. Set MAIL_FROM_ADDRESS or pass --domain=example.com');

            return self::FAILURE;
        }

        $this->info("Checking mail authentication DNS for {$domain}");
        $this->newLine();

        $spfOk = $this->checkSpf($domain);
        $dkimOk = $this->checkDkim($domain);
        $dmarcOk = $this->checkDmarc($domain);

        $this->newLine();
        $this->table(
            ['Check', 'Status'],
            [
                ['SPF', $spfOk ? 'PASS' : 'FAIL'],
                ['DKIM', $dkimOk ? 'PASS' : 'FAIL'],
                ['DMARC', $dmarcOk ? 'PASS' : 'FAIL'],
            ]
        );

        if (! $dkimOk) {
            $this->newLine();
            $this->warn('DKIM is missing. With DMARC p=quarantine, receivers will treat unauthenticated mail as spam.');
            $this->line('Enable DKIM in Google Workspace:');
            $this->line('  1. Google Admin → Apps → Google Workspace → Gmail → Authenticate email');
            $this->line("  2. Generate a new record for {$domain}");
            $this->line('  3. Publish the TXT record at your DNS host (e.g. google._domainkey.'.$domain.')');
            $this->line('  4. Wait for DNS propagation, then Start authentication in Admin');
            $this->line('  5. Re-run: php artisan mail:check-deliverability');
            $this->line('  6. Confirm a live message: Gmail → Show original → SPF/DKIM/DMARC PASS');
            $this->line('  7. Optionally score a test send at https://www.mail-tester.com');
        }

        return ($spfOk && $dkimOk && $dmarcOk) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveDomain(): ?string
    {
        $option = $this->option('domain');

        if (is_string($option) && $option !== '') {
            return strtolower(ltrim($option, '@'));
        }

        $from = (string) config('mail.from.address', '');
        $host = parse_url('mailto:'.$from, PHP_URL_PATH);

        if (is_string($host) && str_contains($host, '@')) {
            return strtolower(substr($host, strrpos($host, '@') + 1));
        }

        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return strtolower(substr($from, strrpos($from, '@') + 1));
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && $appHost !== '' ? strtolower($appHost) : null;
    }

    private function checkSpf(string $domain): bool
    {
        $records = $this->txtRecords($domain);
        $spf = collect($records)->first(fn (string $r) => str_starts_with($r, 'v=spf1'));

        if ($spf === null) {
            $this->error('SPF: no v=spf1 TXT record on '.$domain);

            return false;
        }

        $includesGoogle = str_contains($spf, 'include:_spf.google.com')
            || $this->spfChainIncludesGoogle($spf);

        if ($includesGoogle) {
            $this->info('SPF: '.$spf);

            return true;
        }

        $this->warn('SPF found but does not clearly include Google: '.$spf);

        return false;
    }

    private function spfChainIncludesGoogle(string $spf): bool
    {
        preg_match_all('/include:([^\s]+)/', $spf, $matches);

        foreach ($matches[1] as $includeHost) {
            $nested = collect($this->txtRecords($includeHost))
                ->first(fn (string $r) => str_starts_with($r, 'v=spf1'));

            if ($nested === null) {
                continue;
            }

            if (str_contains($nested, 'include:_spf.google.com') || str_contains($nested, '_spf.google.com')) {
                return true;
            }

            if ($this->spfChainIncludesGoogle($nested)) {
                return true;
            }
        }

        return false;
    }

    private function checkDkim(string $domain): bool
    {
        $required = (string) $this->option('selector');
        $selectors = array_values(array_unique(array_merge([$required], self::DKIM_SELECTORS)));
        $found = [];

        foreach ($selectors as $selector) {
            $host = "{$selector}._domainkey.{$domain}";
            $records = $this->txtRecords($host);

            foreach ($records as $record) {
                if (str_contains($record, 'v=DKIM1') || str_contains($record, 'p=')) {
                    $found[$selector] = $record;
                }
            }
        }

        if ($found === []) {
            $this->error('DKIM: no TXT records found for common selectors on '.$domain);

            return false;
        }

        foreach ($found as $selector => $record) {
            $preview = strlen($record) > 80 ? substr($record, 0, 80).'…' : $record;
            $this->info("DKIM ({$selector}): {$preview}");
        }

        if (! isset($found[$required])) {
            $this->warn("DKIM: selector \"{$required}\" not found (other selectors present).");
        }

        return true;
    }

    private function checkDmarc(string $domain): bool
    {
        $records = $this->txtRecords('_dmarc.'.$domain);
        $dmarc = collect($records)->first(fn (string $r) => str_starts_with($r, 'v=DMARC1'));

        if ($dmarc === null) {
            $this->error('DMARC: no v=DMARC1 TXT record on _dmarc.'.$domain);

            return false;
        }

        $this->info('DMARC: '.$dmarc);

        return true;
    }

    /**
     * @return list<string>
     */
    private function txtRecords(string $host): array
    {
        $fromPhp = $this->txtRecordsViaPhp($host);

        if ($fromPhp !== []) {
            return $fromPhp;
        }

        return $this->txtRecordsViaDig($host);
    }

    /**
     * @return list<string>
     */
    private function txtRecordsViaPhp(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        $out = [];

        foreach ($records as $record) {
            if (! empty($record['txt']) && is_string($record['txt'])) {
                $out[] = $record['txt'];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function txtRecordsViaDig(string $host): array
    {
        $command = 'dig +short TXT '.escapeshellarg($host).' @8.8.8.8 2>/dev/null';
        $output = shell_exec($command);

        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($output)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // dig wraps TXT in quotes; adjacent quoted strings may be split
            $line = str_replace('" "', '', $line);
            $line = trim($line, '"');
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }
}
