<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documentTypes = [
            // Driver Module
            ['name' => 'Authorization for Direct Deposit - Employee Form', 'module' => 'driver', 'status' => true],
            ['name' => 'Clearinghouse Annual Query', 'module' => 'driver', 'status' => true],
            ['name' => 'DInquiry Into Driving Record', 'module' => 'driver', 'status' => true],
            ['name' => 'Medical Examiner National Registry Verification', 'module' => 'driver', 'status' => true],
            ['name' => 'Motor Vehicle Reports (MVR)', 'module' => 'driver', 'status' => true],
            ['name' => 'Pre-Employment Drug Test', 'module' => 'driver', 'status' => true],
            ['name' => 'Pre-Employment Screening Program (PSP) Reports', 'module' => 'driver', 'status' => true],
            ['name' => 'Employer Pull Notice Program', 'module' => 'driver', 'status' => true],
            ['name' => 'Road Test Certificate', 'module' => 'driver', 'status' => true],
            ['name' => 'Road Test', 'module' => 'driver', 'status' => true],
            ['name' => 'Preceding 7 Days Time On Duty', 'module' => 'driver', 'status' => true],
            ['name' => 'Form W-9', 'module' => 'driver', 'status' => true],
            ['name' => 'Safety Performance History Investigation', 'module' => 'driver', 'status' => true],
            
            // Vehicle Module
            ['name' => 'Insurance Certificate', 'module' => 'vehicle', 'status' => true],
            ['name' => 'MC Authority', 'module' => 'vehicle', 'status' => true],
            ['name' => 'Vehicle Registration Certificate', 'module' => 'vehicle', 'status' => true],
            ['name' => 'International Fuel Tax Agreement (IFTA) Decal and License', 'module' => 'vehicle', 'status' => true],
            ['name' => 'Annual Inspection Report', 'module' => 'vehicle', 'status' => true],
            ['name' => '(ELD) Manual', 'module' => 'vehicle', 'status' => true],
            ['name' => 'ELD DOT Card and Instructions', 'module' => 'vehicle', 'status' => true],
            ['name' => 'Master Agreement', 'module' => 'vehicle', 'status' => true],
            
            // Trailer Module
            ['name' => 'Proof of Coverage', 'module' => 'trailer', 'status' => true],
            ['name' => 'Vehicle Registration Certificate', 'module' => 'trailer', 'status' => true],
            ['name' => 'Annual Inspection Report', 'module' => 'trailer', 'status' => true],
            ['name' => '90-Day Inspection Records', 'module' => 'trailer', 'status' => true],
            ['name' => 'Quarterly Inspection Records', 'module' => 'trailer', 'status' => true],
            ['name' => 'Inspection Stickers/Decals', 'module' => 'trailer', 'status' => true],
        ];

        foreach ($documentTypes as $documentType) {
            DocumentType::create($documentType);
        }
    }
}