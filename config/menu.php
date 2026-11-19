<?php

// The sidebar of a company. 'owner' => true: only the owner of the company sees it.
// 'active' is the route pattern that opens the entry. 'supplier' => true: only accounts that look
// for tenders see it; 'buyer' => true: only accounts that post tenders.
return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    ['heading' => 'Find tenders'],
    ['label' => 'All tenders', 'icon' => 'gavel', 'route' => 'tenders.index', 'active' => 'tenders.index'],
    ['label' => 'Matching my alerts', 'icon' => 'bell', 'route' => 'tenders.matching', 'badge' => 'matching', 'supplier' => true],
    ['label' => 'Saved tenders', 'icon' => 'bookmark', 'route' => 'tenders.saved', 'supplier' => true],

    ['heading' => 'Post tenders'],
    ['label' => 'Posted tenders', 'icon' => 'bullhorn', 'route' => 'posted.index', 'active' => 'posted.*', 'buyer' => true],
    ['label' => 'Post a tender', 'icon' => 'plus', 'route' => 'posted.create', 'buyer' => true],

    ['heading' => 'My company'],
    ['label' => 'Company profile', 'icon' => 'building', 'route' => 'settings.edit', 'owner' => true],
    ['label' => 'Tender alerts', 'icon' => 'paper-plane', 'route' => 'alerts.edit', 'owner' => true, 'supplier' => true],
    ['label' => 'Verification', 'icon' => 'shield-halved', 'route' => 'verification.show', 'owner' => true],
    ['label' => 'Team', 'icon' => 'user-group', 'route' => 'team.index', 'active' => 'team.*', 'owner' => true],

    ['heading' => 'Account'],
    ['label' => 'Subscription', 'icon' => 'credit-card', 'route' => 'billing.show'],
];
