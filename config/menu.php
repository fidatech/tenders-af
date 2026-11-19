<?php

// The sidebar of a company. 'owner' => true: only the owner of the company sees it.
// 'active' is the route pattern that opens the entry.
return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    ['heading' => 'Tenders'],
    ['label' => 'All tenders', 'icon' => 'gavel', 'route' => 'tenders.index', 'active' => 'tenders.index'],
    ['label' => 'Matching my alerts', 'icon' => 'bell', 'route' => 'tenders.matching', 'badge' => 'matching'],
    ['label' => 'Saved tenders', 'icon' => 'bookmark', 'route' => 'tenders.saved'],

    ['heading' => 'My company'],
    ['label' => 'Company profile', 'icon' => 'building', 'route' => 'settings.edit', 'owner' => true],
    ['label' => 'Tender alerts', 'icon' => 'paper-plane', 'route' => 'alerts.edit', 'owner' => true],
    ['label' => 'Verification', 'icon' => 'shield-halved', 'route' => 'verification.show', 'owner' => true],
    ['label' => 'Team', 'icon' => 'user-group', 'route' => 'team.index', 'active' => 'team.*', 'owner' => true],

    ['heading' => 'Account'],
    ['label' => 'Subscription', 'icon' => 'credit-card', 'route' => 'billing.show'],
];
