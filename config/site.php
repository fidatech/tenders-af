<?php

// Everything the public website says about itself. The pages, the structured data for search
// engines (JSON-LD) and /llms.txt for AI assistants are all written from this one file, so they
// never disagree. Keep the sentences plain and true to what the site does.
return [
    'tagline' => 'Every tender in Afghanistan, in one place.',

    // one or two sentences that say what it is, for search results and AI answers
    'summary' => 'Tenders.af is a website that lists tenders in Afghanistan from NGOs, UN agencies, government offices and private buyers, together with a free directory of companies. Companies register for free, choose their sectors, and get matching tenders by email and WhatsApp. The full details and documents of a tender open on a paid plan, priced in AFN for Afghan companies and in USD for NGOs and international clients.',

    'description' => 'Find tenders in Afghanistan from NGOs, UN agencies, government and private buyers. Free company registration, alerts by email and WhatsApp, and a directory of verified companies.',

    'steps' => [
        ['Register your company, free', 'Add your profile, your licence and what you supply. Buyers can find you in the company directory.'],
        ['Choose what you want to hear about', 'Tick your sectors, categories and the kinds of tender. Matching tenders reach you by email and WhatsApp.'],
        ['Open the full tender and bid', 'On a paid plan you read the full details, download the documents and work out your bid price on the same page.'],
    ],

    'features' => [
        ['gavel', 'Tenders from every kind of buyer', 'NGOs, UN agencies, government offices, donor projects and private companies, in one list you can search by sector, province, kind of tender and buyer.'],
        ['bell', 'Alerts that match your company', 'You choose your sectors, categories and kinds of tender. A matching tender is sent to you by email and, if you want, by WhatsApp.'],
        ['building', 'Free company directory', 'Every registered company gets a public profile with its sectors, province and contact details. Buyers search it to find suppliers.'],
        ['shield-halved', 'Verified companies', 'A company can send its business licence to be checked. Verified companies carry a badge that buyers can filter for.'],
        ['calculator', 'Bid price calculator', 'On every tender, subscribers can add up their costs, margin and tax to see their bid price.'],
        ['map-location-dot', 'Tenders by province', 'A map of all 34 provinces shows where the open tenders are. Each province and each sector has its own page.'],
    ],

    // Questions people really ask, each with a direct answer first. The placeholders in
    // braces are filled in from the live settings: {price}, {payments}.
    'faqs' => [
        ['What is Tenders.af?', 'Tenders.af is a website that brings tenders in Afghanistan into one place. It lists tenders from NGOs, UN agencies, government offices and private buyers, and it has a free directory of companies. Companies get the tenders that match their sectors by email and WhatsApp.'],
        ['Who can post a tender?', 'Any organisation that buys: a ministry or government office, an NGO, a UN agency or a private company. Register for free, choose "Post tenders", write the tender and send it. We check it and publish it, usually within one working day, and the companies of its sector are told by email and WhatsApp.'],
        ['Does it cost money to post a tender?', 'Posting tenders is free at the moment. Later, posting will need a monthly plan. Registering an organisation stays free.'],
        ['Can one account both post tenders and look for tenders?', 'Yes. One account can do both. You choose it when you register and you can change it in your company profile.'],
        ['Is it free to register a company?', 'Yes. Registering a company is free and stays free. A registered company gets a public profile in the directory, can read the summary and the deadline of every tender, and gets a weekly email of matching tenders.'],
        ['What do I get on a paid plan?', 'A paid plan opens the full details of every tender and its documents, sends you an alert the moment a matching tender is published, and gives you the bid price calculator. The Pro and Business plans add WhatsApp alerts and more users.'],
        ['How much does it cost?', '{price}'],
        ['Why are there prices in AFN and in USD?', 'Afghan companies pay in afghani (AFN). NGOs and international companies or organisations, from any country, pay in US dollars (USD). The plans are the same; only the currency differs.'],
        ['How do I pay?', '{payments} You pay, upload a screenshot or a photo of the payment on your Subscription page, and your plan is switched on after we check it, usually the same day.'],
        ['Can I get a refund or change my plan?', 'Payments are not refunded. You can move to a higher plan at any time: it is paid in full and starts that day. Any other change of plan is made when your paid period ends.'],
        ['How do the tender alerts work?', 'On your company profile you tick the sectors and categories you work in, and under Tender alerts the kinds of tender you want. When a tender is published that matches, you get an email, and a WhatsApp message if you asked for it. On the free plan you get one email a week with the titles of the matching tenders.'],
        ['Where do the tenders come from?', 'Buyers send us their tenders, and our team collects tenders that buyers have published. Each tender names its buyer, and where we found a public notice we link to it.'],
        ['How do I submit a bid?', 'You submit your bid to the buyer, in the way the tender says: by email, in a sealed envelope or in the buyer\'s own system. Tenders.af shows you where and how; it does not submit bids for you.'],
        ['What does "Verified" mean on a company?', 'It means the company sent us its business licence and we checked it. It is not a guarantee of the company\'s work; buyers should still do their own checks.'],
        ['Can a buyer post a tender?', 'Yes. NGOs, companies and other buyers can send us a tender through the contact page, and we publish it after checking it. Posting a tender is free.'],
        ['Which languages does the site speak?', 'The site is in English today. Dari and Pashto are planned.'],
    ],
];
