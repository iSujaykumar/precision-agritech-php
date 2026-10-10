<?php
declare(strict_types=1);

function admin_boot(): array
{
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
    connect_or_explain();
    return require_admin();
}

function admin_open(string $title, string $section = ''): void
{
    render_header($title . ' | Nursery desk', '', 'admin');
    echo '<section class="section admin-page"><div class="wrap">';
    if ($section !== '') {
        admin_tabs($section);
    }
    echo '<h1>' . e($title) . '</h1>';
    $flash = flash_take();
    if ($flash) {
        echo '<p class="flash ' . e($flash['kind']) . '" role="status">' . e($flash['message']) . '</p>';
    }
}

function admin_close(): void
{
    echo '</div></section>';
    render_footer();
}
