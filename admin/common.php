<?php
declare(strict_types=1);

function admin_boot(): array
{
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
    connect_or_explain();
    return require_admin();
}

function admin_open(string $title): void
{
    render_header($title . ' | Nursery desk');
    echo '<section class="section"><div class="wrap">';
    echo '<h1>' . e($title) . '</h1><p class="filters">';
    $links = [
        'Desk' => '/admin',
        'Products' => '/admin/products',
        'Categories' => '/admin/categories',
        'Stock' => '/admin/inventory',
        'Stock history' => '/admin/inventory-history',
        'Orders' => '/admin/orders',
        'Customers' => '/admin/customers',
        'Reviews' => '/admin/reviews',
        'Coupons' => '/admin/coupons',
        'Messages' => '/admin/messages',
        'Wholesale' => '/admin/wholesale',
        'Settings' => '/admin/settings',
        'Staff' => '/admin/staff',
        'Audit' => '/admin/audit-log',
    ];
    foreach ($links as $label => $href) {
        echo '<a href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '</p>';
}

function admin_close(): void
{
    echo '</div></section>';
    render_footer();
}
