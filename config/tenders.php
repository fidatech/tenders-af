<?php

return [
    // may a new company sign up by itself on /register?
    'registration' => (bool) env('REGISTRATION_OPEN', true),

    // who is told by email when a company says it has paid (commas between several).
    // Empty: every administrator who may sign in.
    'billing_email' => env('BILLING_EMAIL'),


    // Why a payment can be rejected. The administrator picks one; the business reads "text" and "advice".
    'reject_reasons' => [
        'not_received' => [
            'label' => 'Money not received',
            'text' => 'We did not receive this payment in our account.',
            'advice' => 'Please check in your payment app that the transfer went through, then send the screenshot of the completed payment.',
        ],
        'less_amount' => [
            'label' => 'Amount is less than the price',
            'text' => 'The amount you paid is less than the price of the plan.',
            'advice' => 'Please pay the rest, then send the screenshots of both payments or of the full amount.',
        ],
        'wrong_screenshot' => [
            'label' => 'Screenshot is not a payment',
            'text' => 'The picture you sent is not a screenshot of a payment.',
            'advice' => 'Please send the screenshot of the payment itself, the one your payment app shows after the money is sent.',
        ],
        'unclear' => [
            'label' => 'Screenshot cannot be read',
            'text' => 'The screenshot is not clear enough to read the amount, the date or the number.',
            'advice' => 'Please send a clear, uncropped screenshot that shows the amount, the date and the receiving number.',
        ],
        'wrong_account' => [
            'label' => 'Paid to another number',
            'text' => 'The payment was sent to a different account or number, not to ours.',
            'advice' => 'Please pay to the number or QR code shown on the Subscription page, then send that screenshot.',
        ],
        'already_used' => [
            'label' => 'Screenshot already used',
            'text' => 'This screenshot was already used for another payment.',
            'advice' => 'Each payment needs its own screenshot. Please send the screenshot of the new payment.',
        ],
        'old_payment' => [
            'label' => 'Old payment',
            'text' => 'The payment in the screenshot is an old one and does not belong to this subscription.',
            'advice' => 'Please send the screenshot of the payment you made for this subscription.',
        ],
        'failed' => [
            'label' => 'Payment failed or was returned',
            'text' => 'The payment in the screenshot failed or was returned to you.',
            'advice' => 'Please pay again and send the screenshot of the completed payment.',
        ],
    ],

    // days a new company may use a paid plan before it must pay (only used when no free plan is offered)
    'trial_days' => (int) env('TRIAL_DAYS', 7),

    // kinds of tender: key => [short name, full name]
    'types' => [
        'rfq' => ['RFQ', 'Request for quotation'],
        'rfp' => ['RFP', 'Request for proposal'],
        'itb' => ['ITB', 'Invitation to bid'],
        'eoi' => ['EOI', 'Expression of interest'],
        'prequalification' => ['PQ', 'Pre-qualification'],
        'auction' => ['AUC', 'Auction'],
    ],

    // who is buying
    'buyer_types' => [
        'ngo' => 'NGO',
        'un' => 'UN agency',
        'government' => 'Government',
        'private' => 'Private company',
        'donor' => 'Donor project',
    ],

    // how bids are handed in
    'submission_methods' => [
        'email' => 'By email',
        'sealed' => 'Sealed envelope',
        'online' => 'Online system',
        'other' => 'Other',
    ],

    // the size of a tender when the buyer gives no budget (estimated by the data team)
    'size_bands' => [
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'xlarge' => 'Very large',
    ],

    // currencies a tender budget can be given in
    'currencies' => ['AFN', 'USD', 'EUR', 'PKR'],

    // WhatsApp alerts go through your own sender service: a POST with "to" and "message".
    // Both can also be set under /admin > Settings.
    'whatsapp_url' => env('WHATSAPP_API_URL'),
    'whatsapp_token' => env('WHATSAPP_API_TOKEN'),
];
