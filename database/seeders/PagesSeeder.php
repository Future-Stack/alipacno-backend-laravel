<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Page::updateOrCreate(
            ['slug' => Str::slug("Terms & Conditions")],
            [
                'title' => "Terms & Conditions",

                'content' => [
                    'heading' => "Terms and Conditions",

                    'intro' => "Please read these Terms carefully before using Pacinos.uk or placing an order. By placing an order, you agree to the Terms that apply at the time of that order. Nothing in these Terms affects your statutory rights as a consumer.",

                    'sections' => [
                        [
                            'title' => '1. About us',
                            'body' => [
                                "Pacinos.uk (the “Website”) is operated by Pacino's Ltd (“Pacino's”, “we”, “us” or “our”), a company registered in England and Wales under company number 16758957. Our registered office is 156 Well Hall Road, London, SE9 6SN.",
                                "The Website allows customers to browse our menu, create or use a customer account, place orders for delivery or collection, make payments, receive order updates and access promotions and other Pacino's services.",
                                "Unless we clearly tell you before checkout that another legal entity is the seller, your contract for an order placed through the Website is with Pacino's Ltd.",
                            ],
                        ],

                        [
                            'title' => '2. Using the Website',
                            'body' => [
                                "You may use the Website only for lawful purposes. You must not misuse the Website, attempt unauthorised access, introduce malicious software, interfere with its operation, scrape or extract content at scale without permission, impersonate another person, make fraudulent orders or use the Website in a way that infringes the rights of others.",
                                "We may suspend or restrict access where reasonably necessary for security, fraud prevention, legal compliance or serious misuse. This does not affect rights you already have in relation to an accepted order.",
                            ],
                        ],

                        [
                            'title' => '3. Customer accounts',
                            'body' => [
                                "You may be able to order as a guest or create an account, depending on the functionality available. You must provide accurate and current information.",
                                "You are responsible for keeping your login credentials secure. Please tell us promptly if you believe your account has been compromised. We may require reasonable identity or security checks before making changes to an account.",
                                "Marketing preferences are separate from your customer account. Creating an account, accepting these Terms or placing an order does not by itself amount to consent to receive marketing where consent is legally required.",
                            ],
                        ],

                        [
                            'title' => '4. Menu information, products and availability',
                            'body' => [
                                "We take reasonable care to describe our products accurately. Product images are illustrative and the appearance, presentation or packaging of the product you receive may differ.",
                                "All products are subject to availability. If an item becomes unavailable after you order, we may contact you about a suitable alternative or remove the item and refund the relevant amount.",
                                "We may change our menu, recipes, portions, ingredients, availability and prices from time to time. Changes will not alter an accepted order unless agreed with you or reasonably required to deal with an unavailable item, in which case your legal rights remain unaffected.",
                            ],
                        ],

                        [
                            'title' => '5. Allergies and dietary requirements',
                            'body' => [
                                "Food allergies and intolerances can be serious. Allergen information should be reviewed before ordering. If you or the person consuming the food has an allergy or intolerance, please check the allergen information made available through the Website and contact the relevant Pacino's store before ordering if you require further information.",
                                "Although we use procedures intended to manage allergens, our kitchens handle multiple ingredients and cross-contact may be possible. We will not exclude or limit liability where it would be unlawful to do so, including where injury is caused by our negligence.",
                                "Do not rely solely on photographs, product names or previous orders when making decisions about allergens because recipes and ingredients may change.",
                            ],
                        ],

                        [
                            'title' => '6. Placing an order',
                            'body' => [
                                "Your basket is not an accepted order. When you submit an order through the Website, you are offering to buy the items in that order.",
                                "Before you place the order, you will have an opportunity to review key information including the items, quantities, prices, applicable charges and delivery or collection details. You are responsible for checking that these details are correct.",
                                "A contract is formed when we accept your order, normally by displaying or sending an order confirmation. An automated acknowledgement that we have received your request does not necessarily mean that the order has been accepted if it clearly states otherwise.",
                            ],
                        ],

                        [
                            'title' => '7. When we may refuse or cancel an order',
                            'body' => [
                                "Before acceptance, we may refuse an order where, for example, a product is unavailable, payment cannot be authorised, the delivery address is outside our service area, there is an obvious pricing or description error, the order cannot reasonably be fulfilled, or we reasonably suspect fraud, abuse or unlawful activity.",
                                "After acceptance, we may need to cancel all or part of an order where fulfilment becomes impossible or unlawful. Where you have paid for items we do not supply, we will refund the relevant amount using an appropriate method.",
                            ],
                        ],

                        [
                            'title' => '8. Prices and charges',
                            'body' => [
                                "Prices are displayed in pounds sterling. Prices include VAT where applicable unless clearly stated otherwise.",
                                "Any delivery charge, service charge or other mandatory charge applicable to an order will be shown before you place the order. The total payable will be displayed at checkout.",
                                "We try to ensure prices are correct. If an obvious pricing error is discovered before acceptance, we may reject the order and invite you to order at the correct price. If an error is discovered after acceptance, we will act reasonably and in accordance with applicable consumer law.",
                            ],
                        ],

                        [
                            'title' => '9. Payment',
                            'body' => [
                                "You must use a payment method accepted by the Website. Payment may be processed by a third-party payment provider and may be subject to security or authentication checks.",
                                "By submitting payment details, you confirm that you are authorised to use the payment method. If payment is declined or cannot be verified, the order may not be accepted.",
                                "Where the Website offers cash or another payment method, any conditions for that method will be shown during checkout.",
                            ],
                        ],

                        [
                            'title' => '10. Delivery',
                            'body' => [
                                "If you select delivery, you must provide a complete and accurate delivery address, contact details and any necessary delivery instructions.",
                                "Delivery times shown on the Website are estimates unless we expressly agree a guaranteed time. Preparation time, demand, traffic, weather, distance and circumstances outside our reasonable control can affect delivery.",
                                "You should be available to receive the order and ensure we can reasonably access the delivery location. If we cannot complete delivery because the address or contact details supplied are incorrect, access is not reasonably available, or nobody is available to receive the order, please contact us. Because freshly prepared food is perishable, redelivery or a refund may not always be possible where the failed delivery was caused by the customer, subject always to your statutory rights.",
                            ],
                        ],

                        [
                            'title' => '11. Collection',
                            'body' => [
                                "If you select collection, the Website will identify the relevant collection location and provide an estimated collection time.",
                                "Please do not rely on an estimated time as a guarantee that the order will be ready at that exact moment. You may be asked to provide your name, order number or other reasonable evidence to identify the order.",
                                "Freshly prepared food should be collected within a reasonable time. We cannot guarantee quality or temperature where an order is collected substantially later than the stated collection time.",
                            ],
                        ],

                        [
                            'title' => '12. Changes and cancellations by you',
                            'body' => [
                                "If you need to change or cancel an order, contact the relevant store as quickly as possible. We may be able to accommodate a request before food preparation begins, but we cannot guarantee this.",
                                "Because many Pacino's products are freshly prepared, personalised to an order, or liable to deteriorate or expire rapidly, the statutory 14-day change-of-mind cancellation right applicable to some distance contracts will generally not apply to those products.",
                                "This does not affect your rights where goods are faulty, unsafe, not as described or otherwise do not conform to the contract.",
                            ],
                        ],

                        [
                            'title' => '13. Problems with an order, refunds and replacements',
                            'body' => [
                                "If there is a problem with your order, please contact us or the fulfilling store as soon as reasonably possible and provide the order details.",
                                "Depending on the circumstances and your legal rights, an appropriate remedy may include replacing an item, refunding an affected item, refunding an order or another reasonable resolution.",
                                "Nothing in these Terms restricts remedies available under the Consumer Rights Act 2015 or other applicable consumer law. Refunds, where due, will normally be returned to the original payment method unless another method is reasonably agreed.",
                            ],
                        ],

                        [
                            'title' => '14. Promotions, vouchers and discount codes',
                            'body' => [
                                "Promotions, voucher codes, meal deals, loyalty offers and discounts may have separate conditions. These may include an expiry date, minimum spend, participating locations, eligible products, customer eligibility, collection/delivery restrictions or a limit on the number of uses.",
                                "Unless the particular offer states otherwise, offers cannot be exchanged for cash, cannot normally be applied after an order has been placed and may not be combined with another promotion.",
                                "We may refuse or withdraw a promotion where it has been used fraudulently, unlawfully or contrary to its stated conditions. We will not retrospectively remove a discount from an order already accepted merely because we later decide to end the promotion.",
                            ],
                        ],

                        [
                            'title' => '15. Marketing communications',
                            'body' => [
                                "We may offer you the choice to receive Pacino's offers, product news and promotions by email, SMS/text or other channels.",
                                "Our use of personal information for marketing is governed by our Privacy Policy and applicable data-protection and electronic-marketing law. Where consent is required, marketing consent is optional and separate from accepting these Terms or completing an order.",
                                "Where permitted by law, we may market our own similar products or services to existing customers under the applicable existing-customer or “soft opt-in” rules, provided the legal requirements are met.",
                                "You can unsubscribe or object to direct marketing at any time using the method provided in the communication or the preference controls made available by us. Service communications about an order, payment, account security or another service you requested may still be sent where necessary.",
                            ],
                        ],

                        [
                            'title' => '16. Privacy and cookies',
                            'body' => [
                                "We process personal information in accordance with our Privacy Policy. The Website should also provide information about cookies and similar technologies through its Cookie Policy and cookie controls.",
                                "Our Privacy Policy explains, among other things, what information we collect, why we use it, the lawful bases we rely on, who we share it with, retention, marketing preferences and your data-protection rights.",
                            ],
                        ],

                        [
                            'title' => '17. Intellectual property',
                            'body' => [
                                "The Website and its content, including Pacino's branding, logos, graphics, photographs, menu presentation, text, software and design, are owned by or licensed to Pacino's unless stated otherwise and are protected by intellectual-property laws.",
                                "You may view and use the Website for your personal, non-commercial use. You must not reproduce, distribute, commercially exploit, alter or use protected material without permission except where the law permits.",
                            ],
                        ],

                        [
                            'title' => '18. Website availability and changes',
                            'body' => [
                                "We aim to provide a reliable Website but do not promise that it will always be uninterrupted or error-free. We may temporarily suspend access for maintenance, security, technical issues or upgrades.",
                                "We may update, redesign or change Website features. We will not use a Website change to unlawfully remove rights relating to an order already accepted.",
                            ],
                        ],

                        [
                            'title' => '19. Third-party services and links',
                            'body' => [
                                "The Website may use or link to services provided by third parties, including payment, maps, delivery, analytics or social-media services. We are not responsible for the content of an independent third-party website merely because we link to it.",
                                "Where a third party processes personal information on our behalf or receives information in connection with a service, this will be handled in accordance with applicable data-protection law and our Privacy Policy.",
                            ],
                        ],

                        [
                            'title' => '20. Our responsibility to you',
                            'body' => [
                                "We do not exclude or limit liability where doing so would be unlawful. This includes liability for death or personal injury caused by our negligence, fraud or fraudulent misrepresentation, and liability that cannot be excluded under consumer law.",
                                "If you are a consumer, we are responsible for loss or damage that is a foreseeable result of our breach of these Terms or our failure to use reasonable care and skill. Loss is foreseeable if it was obvious that it would happen or both you and we knew it might happen when the contract was made.",
                                "If you use the Website as a consumer, we are not responsible for business losses such as loss of profit, revenue, business opportunity or business interruption arising from private consumer use.",
                            ],
                        ],

                        [
                            'title' => '21. Events outside our reasonable control',
                            'body' => [
                                "We are not responsible for delay or failure caused by events outside our reasonable control, but this does not remove any rights you have under consumer law. Where such an event materially affects an accepted order, we will take reasonable steps to minimise the impact and, where appropriate, contact you about cancellation, refund or alternative fulfilment.",
                            ],
                        ],

                        [
                            'title' => '22. Complaints and customer support',
                            'body' => [
                                "If you have a complaint or need help with an order, please use the customer-support/contact details shown on Pacinos.uk or contact the relevant Pacino's store shown on your order confirmation.",
                                "Please provide your order number and enough information for us to investigate. We will aim to deal with complaints fairly and within a reasonable time.",
                            ],
                        ],

                        [
                            'title' => '23. Changes to these Terms',
                            'body' => [
                                "We may update these Terms from time to time to reflect changes to the Website, our services, business operations or applicable law.",
                                "The version in force when you place an order will normally govern that order. The current version and effective date will be published on Pacinos.uk.",
                            ],
                        ],

                        [
                            'title' => '24. General',
                            'body' => [
                                "If any provision of these Terms is found to be unlawful or unenforceable, the remaining provisions will continue to apply.",
                                "A delay by us in enforcing a right does not mean we have waived that right. These Terms are between you and us; no other person has a right to enforce them except where the law expressly provides otherwise.",
                            ],
                        ],

                        [
                            'title' => '25. Governing law and courts',
                            'body' => [
                                "These Terms are governed by the law of England and Wales.",
                                "If you are a consumer resident elsewhere in the United Kingdom, you retain any mandatory protections and jurisdiction rights available to you under applicable law. Subject to those rights, disputes may be dealt with by the courts of England and Wales.",
                            ],
                        ],

                        [
                            'title' => '26. Contact details',
                            'body' => [
                                "Pacino's Ltd",
                                "156 Well Hall Road",
                                "London",
                                "SE9 6SN",
                                "Company number: 16758957",
                                "Website: pacinos.uk",
                            ],
                        ],
                    ],
                ],

                'status' => true,
            ]
        );
    }
}

