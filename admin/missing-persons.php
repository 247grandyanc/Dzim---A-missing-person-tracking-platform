<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Set page variables
$title = "Missing Persons Management";
$page_title = "Missing Persons & Reward System";
$breadcrumbs = [
    ['text' => 'Dashboard', 'url' => 'dashboard.php'],
    ['text' => 'Missing Persons', 'url' => 'missing-persons.php']
];

require_once __DIR__ . '/includes/admin-nav.php';

// Handle different actions
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'view':
        require __DIR__ . '/includes/missing-persons/view.php';
        break;
    case 'edit':
        require __DIR__ . '/includes/missing-persons/edit.php';
        break;
    case 'resolve':
        require __DIR__ . '/includes/missing-persons/resolve.php';
        break;
    case 'rewards':
        require __DIR__ . '/includes/missing-persons/rewards.php';
        break;
    case 'claims':
        require __DIR__ . '/includes/missing-persons/claims.php';
        break;
    default:
        require __DIR__ . '/includes/missing-persons/list.php';
}

require_once __DIR__ . '/includes/admin-footer.php';
?>