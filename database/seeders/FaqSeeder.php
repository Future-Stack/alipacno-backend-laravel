<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'How do I report a successful delivery?',
                'answers'  => 'After completing the delivery, open the delivery details and mark the delivery as completed. Make sure all required delivery information has been provided.',
                'status'   => 1,
            ],
            [
                'question' => 'How can I update my vehicle or personal information?',
                'answers'  => 'Go to your Account Settings and update your personal or vehicle information. Make sure the information you provide is accurate and up to date.',
                'status'   => 1,
            ],
            [
                'question' => 'How do I get paid?',
                'answers'  => 'Your payment is processed according to the delivery payment schedule. You can check your payment and earnings information from your account.',
                'status'   => 1,
            ],
            [
                'question' => 'How do I start my delivery route?',
                'answers'  => 'Open your assigned delivery route from the Deliveries section and follow the route details provided. Start the route when you are ready to begin your assigned deliveries.',
                'status'   => 1,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answers' => $faq['answers'],
                    'status'  => $faq['status'],
                ]
            );
        }
    }
}