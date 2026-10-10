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
    render_header($title . ' | Nursery desk', '', 'admin');
    echo '<section class="section admin-page"><div class="wrap">';
    echo '<h1>' . e($title) . '</h1>';
}

function admin_close(): void
{
    echo '</div></section>';
    render_footer();
}
