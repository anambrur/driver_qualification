<?php

namespace Tests\Feature\Modules\Companies;

use App\Models\Company;
use Illuminate\Http\UploadedFile;

/**
 * Fixtures for the company account and policy PDF screens.
 */
final class Companies
{
    /**
     * A valid update payload that keeps every field as it is, merged with $overrides.
     */
    public static function updatePayload(Company $company, array $overrides = []): array
    {
        return array_merge([
            'company_name' => $company->company_name,
            'email' => $company->email,
            'address' => $company->address ?? '1 Main St',
            'city' => $company->city ?? 'Austin',
            'state' => $company->state_code ?? 'TX',
            'zip' => $company->zip ?? '73301',
            'phone' => $company->phone,
            'status' => $company->status,
        ], $overrides);
    }

    public static function storePayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme Freight',
            'email' => 'owner@acme-freight.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'address' => '1 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip' => '73301',
            'status' => 'active',
        ], $overrides);
    }

    /**
     * A real upload whose MIME type is sniffed from its content, as in production.
     */
    public static function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public static function svg(string $name = 'logo.svg'): UploadedFile
    {
        return self::realUpload($name, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    }

    public static function pdf(string $name = 'policy.pdf'): UploadedFile
    {
        return self::realUpload($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }
}
