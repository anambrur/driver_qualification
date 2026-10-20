<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * The homepage testimonials and FAQ it shipped with. Only fills empty tables, so it never
 * overwrites what platform staff entered. The rest of the homepage copy defaults in App\Support\Landing.
 */
class LandingContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! Testimonial::query()->exists()) {
            foreach ([
                ['quote' => 'We went from paper files and spreadsheets to being fully DOT audit-ready in a week. This platform saved us from a potential $16,000 fine.', 'name' => 'Marcus Johnson', 'role' => 'Fleet Manager', 'company' => 'JM Logistics, Texas'],
                ['quote' => 'The expiration alerts alone are worth the price. We used to miss renewals constantly. Now everything is automated and we sleep better at night.', 'name' => 'Sarah Chen', 'role' => 'Compliance Officer', 'company' => 'Pacific Freight, California'],
                ['quote' => 'Onboarding new drivers used to take 3 days of paperwork. Now it takes 30 minutes with the digital application. Our drivers love how easy it is.', 'name' => 'Robert Williams', 'role' => 'Owner-Operator', 'company' => 'Williams Trucking, Ohio'],
            ] as $i => $testimonial) {
                Testimonial::query()->create($testimonial + ['rating' => 5, 'is_active' => true, 'sort_order' => $i + 1]);
            }
        }

        if (! Faq::query()->exists()) {
            foreach ([
                ['question' => 'What is a Driver Qualification File (DQF)?', 'answer' => 'A Driver Qualification File is a federally-mandated set of documents that motor carriers must maintain for every driver they employ. Required under 49 CFR Part 391, it includes the driver\'s application, MVR, road test certificate, medical examiner\'s certificate, and more. Our platform digitizes and automates the entire process.'],
                ['question' => 'What documents are required under 49 CFR Part 391?', 'answer' => 'Key required documents include: Driver application for employment, MVR (Motor Vehicle Record) pulled annually, Medical Examiner\'s Certificate, Road test certificate or equivalent, Driver\'s license copy, Previous employer Safety Performance History, FMCSA Clearinghouse query, and Alcohol & drug testing records.'],
                ['question' => 'How do automated expiration alerts work?', 'answer' => 'Our system continuously monitors expiration dates for all driver documents — CDLs, medical cards, MVRs, and more. You\'ll receive automated email notifications at 90, 60, 30, and 7 days before expiration, ensuring you never miss a renewal deadline.'],
                ['question' => 'Can drivers submit documents from their phones?', 'answer' => 'Yes! Our digital application process is fully mobile-responsive. Drivers can complete the 10-step onboarding workflow, upload photos of documents, and sign consent forms directly from their smartphones — no app download required.'],
                ['question' => 'Is my data secure?', 'answer' => 'Absolutely. We use enterprise-grade encryption for all data at rest and in transit. Our platform features role-based access controls, audit trails, and secure document storage. Your sensitive driver information is protected with industry-leading security standards.'],
                ['question' => 'Can I try the platform before committing?', 'answer' => 'Yes! We offer a free 14-day trial with full access to all features. No credit card required to start. You can also schedule a personalized demo with our team to see the platform in action.'],
            ] as $i => $faq) {
                Faq::query()->create($faq + ['is_active' => true, 'sort_order' => $i + 1]);
            }
        }
    }
}
