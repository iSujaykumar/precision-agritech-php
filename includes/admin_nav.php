<?php
declare(strict_types=1);

function orders_filter_sql(array $get): array
{
    $where = ' WHERE 1=1';
    $params = [];
    $allowed = ['placed', 'payment_pending', 'payment_submitted', 'payment_confirmed', 'confirmed', 'preparing', 'packed', 'out_for_delivery', 'dispatched', 'delivery_failed', 'delivered', 'cancelled'];
    $bucket = (string) ($get['bucket'] ?? '');
    $status = (string) ($get['status'] ?? '');
    $pay = (string) ($get['pay'] ?? '');
    if (($get['check'] ?? '') === '1') {
        $where .= " AND status = 'payment_submitted'";
    } elseif ($bucket === 'new') {
        $where .= " AND status IN ('placed','payment_pending')";
    } elseif ($bucket === 'pack') {
        $where .= " AND status IN ('payment_confirmed','confirmed','preparing')";
    } elseif ($bucket === 'dispatch') {
        $where .= " AND status IN ('confirmed','preparing','packed')";
    } elseif ($bucket === 'today') {
        $where .= ' AND created_at >= CURDATE()';
    } elseif ($bucket === 'week') {
        $where .= ' AND created_at >= (NOW() - INTERVAL 7 DAY)';
    } elseif (in_array($status, $allowed, true)) {
        $where .= ' AND status = ?';
        $params[] = $status;
    }
    if ($pay === 'paid') {
        $where .= " AND payment_status = 'paid'";
    } elseif ($pay === 'unpaid') {
        $where .= " AND payment_status IN ('unpaid','pending') AND status <> 'cancelled'";
    } elseif ($pay === 'refund_due') {
        $where .= " AND payment_status = 'refund_due'";
    }
    $q = trim((string) ($get['q'] ?? ''));
    if ($q !== '') {
        $where .= ' AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)';
        $like = '%' . str_replace(['%', '_'], '', $q) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    return [$where, $params];
}

function stock_rows(string $filter): array
{
    $low = low_stock_limit();
    $sql = 'SELECT id, name, variety, stock_qty, reserved_qty, active, list_only, slug FROM products WHERE parent_product_id IS NULL AND farmer_only = 0';
    if ($filter === 'low' || $filter === 'out') {
        $sql .= ' AND active = 1 AND list_only = 0';
        if ($filter === 'low') {
            $sql .= ' AND (stock_qty - reserved_qty) > 0 AND (stock_qty - reserved_qty) <= ' . $low;
        } else {
            $sql .= ' AND (stock_qty - reserved_qty) <= 0';
        }
    }
    $sql .= ' ORDER BY name';
    return db()->query($sql)->fetchAll();
}

function photo_rows(bool $onlyPlaceholder): array
{
    $sql = 'SELECT id, name, slug, image_is_placeholder FROM products WHERE parent_product_id IS NULL AND farmer_only = 0 AND list_only = 0 AND active = 1';
    if ($onlyPlaceholder) {
        $sql .= ' AND image_is_placeholder = 1';
    }
    $sql .= ' ORDER BY name';
    return db()->query($sql)->fetchAll();
}

function live_offer_rows(): array
{
    $rows = db()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE ' . catalog_card_sql() . ' ORDER BY p.name')->fetchAll();
    $live = [];
    foreach ($rows as $row) {
        if (offer_is_listed($row)) {
            $live[] = $row;
        }
    }
    return $live;
}

function simple_total(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function admin_link_count(string $href): int
{
    $parts = parse_url($href) ?: [];
    $path = rtrim((string) ($parts['path'] ?? ''), '/') ?: '/';
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    if ($path === '/admin/orders') {
        [$where, $params] = orders_filter_sql($query);
        return simple_total('SELECT COUNT(*) FROM orders' . $where, $params);
    }
    if ($path === '/admin/stock') {
        return count(stock_rows((string) ($query['filter'] ?? '')));
    }
    if ($path === '/admin/photos') {
        return count(photo_rows(((string) ($query['filter'] ?? '')) === 'placeholder'));
    }
    if ($path === '/admin/offers') {
        return count(live_offer_rows());
    }
    if ($path === '/admin/reviews') {
        $status = (string) ($query['status'] ?? '');
        if (in_array($status, ['pending', 'approved', 'rejected', 'hidden'], true)) {
            return simple_total('SELECT COUNT(*) FROM reviews WHERE status = ?', [$status]);
        }
        return simple_total('SELECT COUNT(*) FROM reviews');
    }
    if ($path === '/admin/messages') {
        $status = (string) ($query['status'] ?? '');
        if (in_array($status, ['new', 'handled'], true)) {
            return simple_total('SELECT COUNT(*) FROM contact_messages WHERE status = ?', [$status]);
        }
        return simple_total('SELECT COUNT(*) FROM contact_messages');
    }
    if ($path === '/admin/wholesale') {
        $status = (string) ($query['status'] ?? '');
        if (in_array($status, ['new', 'handled'], true)) {
            return simple_total('SELECT COUNT(*) FROM wholesale_requests WHERE status = ?', [$status]);
        }
        return simple_total('SELECT COUNT(*) FROM wholesale_requests');
    }
    if ($path === '/admin/restock') {
        try {
            return simple_total("SELECT COUNT(*) FROM restock_requests WHERE status = 'new'");
        } catch (Throwable $err) {
            return 0;
        }
    }
    return 0;
}

function admin_metrics(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $defs = [
        ['key' => 'pay_confirm', 'label' => 'Payments to confirm', 'href' => '/admin/orders?status=payment_submitted', 'band' => 'now', 'priority' => 1],
        ['key' => 'new_orders', 'label' => 'New orders to accept', 'href' => '/admin/orders?bucket=new', 'band' => 'now', 'priority' => 2],
        ['key' => 'to_pack', 'label' => 'Orders to pack', 'href' => '/admin/orders?bucket=pack', 'band' => 'now', 'priority' => 3],
        ['key' => 'refunds', 'label' => 'Refunds due', 'href' => '/admin/orders?pay=refund_due', 'band' => 'now', 'priority' => 4],
        ['key' => 'restock', 'label' => 'Back-in-stock requests', 'href' => '/admin/restock', 'band' => 'now', 'priority' => 5],
        ['key' => 'today', 'label' => 'Today', 'href' => '/admin/orders?bucket=today', 'band' => 'orders'],
        ['key' => 'week', 'label' => 'This week', 'href' => '/admin/orders?bucket=week', 'band' => 'orders'],
        ['key' => 'dispatch', 'label' => 'Ready to dispatch', 'href' => '/admin/orders?bucket=dispatch', 'band' => 'orders'],
        ['key' => 'unpaid', 'label' => 'Unpaid', 'href' => '/admin/orders?pay=unpaid', 'band' => 'orders'],
        ['key' => 'low', 'label' => 'Low stock', 'href' => '/admin/stock?filter=low', 'band' => 'stock'],
        ['key' => 'out', 'label' => 'Out of stock', 'href' => '/admin/stock?filter=out', 'band' => 'stock'],
        ['key' => 'photos', 'label' => 'Plants needing a photo', 'href' => '/admin/photos?filter=placeholder', 'band' => 'stock'],
        ['key' => 'offers', 'label' => 'Active offers', 'href' => '/admin/offers?live=1', 'band' => 'shop'],
        ['key' => 'reviews', 'label' => 'Pending reviews', 'href' => '/admin/reviews?status=pending', 'band' => 'shop'],
        ['key' => 'messages', 'label' => 'New messages', 'href' => '/admin/messages?status=new', 'band' => 'shop'],
        ['key' => 'wholesale', 'label' => 'New wholesale requests', 'href' => '/admin/wholesale?status=new', 'band' => 'shop'],
    ];
    foreach ($defs as &$row) {
        $row['count'] = admin_link_count($row['href']);
    }
    unset($row);
    $cache = $defs;
    return $cache;
}

function admin_metric(string $key): array
{
    foreach (admin_metrics() as $row) {
        if ($row['key'] === $key) {
            return $row;
        }
    }
    return ['key' => $key, 'label' => $key, 'href' => '/admin', 'band' => '', 'count' => 0];
}

function section_for_path(string $path): string
{
    $map = [
        '/admin' => 'today',
        '/admin/orders' => 'orders',
        '/admin/order-view' => 'orders',
        '/admin/deliveries' => 'orders',
        '/admin/claims' => 'orders',
        '/admin/invoice' => 'orders',
        '/admin/invoices' => 'orders',
        '/admin/products' => 'plants',
        '/admin/product-edit' => 'plants',
        '/admin/stock' => 'plants',
        '/admin/inventory' => 'plants',
        '/admin/inventory-history' => 'plants',
        '/admin/photos' => 'plants',
        '/admin/categories' => 'plants',
        '/admin/attention' => 'plants',
        '/admin/restock' => 'plants',
        '/admin/prices' => 'offers',
        '/admin/price-lists' => 'offers',
        '/admin/offers' => 'offers',
        '/admin/coupons' => 'offers',
        '/admin/customers' => 'customers',
        '/admin/customer-view' => 'customers',
        '/admin/reviews' => 'customers',
        '/admin/messages' => 'messages',
        '/admin/wholesale' => 'messages',
        '/admin/homepage' => 'homepage',
        '/admin/months' => 'homepage',
        '/admin/labels' => 'homepage',
        '/admin/pages' => 'homepage',
        '/admin/settings' => 'settings',
        '/admin/phones' => 'settings',
        '/admin/banks' => 'settings',
        '/admin/payments' => 'settings',
        '/admin/delivery' => 'settings',
        '/admin/cod-block' => 'settings',
        '/admin/staff' => 'settings',
        '/admin/mail-log' => 'settings',
        '/admin/health' => 'settings',
        '/admin/update' => 'settings',
        '/admin/migrate' => 'settings',
        '/admin/audit-log' => 'settings',
        '/admin/help' => 'help',
        '/admin/finder' => 'help',
    ];
    return $map[$path] ?? 'today';
}

function admin_groups(): array
{
    $orders = 0;
    $messages = 0;
    try {
        $orders = admin_metric('pay_confirm')['count'] + admin_metric('new_orders')['count'];
        $messages = admin_metric('messages')['count'] + admin_metric('wholesale')['count'];
    } catch (Throwable $err) {
        $orders = 0;
        $messages = 0;
    }
    return [
        'today' => ['label' => 'Today', 'href' => '/admin', 'count' => 0],
        'orders' => ['label' => 'Orders', 'href' => '/admin/orders', 'count' => $orders],
        'plants' => ['label' => 'Plants', 'href' => '/admin/products', 'count' => 0],
        'offers' => ['label' => 'Prices & Offers', 'href' => '/admin/prices', 'count' => 0],
        'customers' => ['label' => 'Customers', 'href' => '/admin/customers', 'count' => 0],
        'messages' => ['label' => 'Messages', 'href' => '/admin/messages', 'count' => $messages],
        'homepage' => ['label' => 'Homepage', 'href' => '/admin/homepage', 'count' => 0],
        'settings' => ['label' => 'Settings', 'href' => '/admin/settings', 'count' => 0],
        'help' => ['label' => 'Help', 'href' => '/admin/help', 'count' => 0],
    ];
}

function section_tabs(): array
{
    return [
        'plants' => [
            'All plants' => '/admin/products',
            'Stock' => '/admin/stock',
            'Photos' => '/admin/photos',
            'Categories' => '/admin/categories',
            'Needs attention' => '/admin/attention',
            'Back in stock' => '/admin/restock',
        ],
        'offers' => [
            'Price quick edit' => '/admin/prices',
            'Price lists' => '/admin/price-lists',
            'Offers' => '/admin/offers',
            'Coupons' => '/admin/coupons',
        ],
        'orders' => [
            'All orders' => '/admin/orders',
            'Payments to confirm' => '/admin/orders?status=payment_submitted',
            'To pack' => '/admin/orders?bucket=pack',
            'Deliveries today' => '/admin/deliveries',
            'Claims' => '/admin/claims',
            'Invoices' => '/admin/invoices',
        ],
        'customers' => [
            'Customers' => '/admin/customers',
            'Reviews' => '/admin/reviews',
        ],
        'messages' => [
            'Messages' => '/admin/messages',
            'Wholesale' => '/admin/wholesale',
        ],
        'homepage' => [
            'Sections' => '/admin/homepage',
            'Plant this month' => '/admin/months',
            'Shop labels' => '/admin/labels',
            'Pages' => '/admin/pages',
        ],
        'settings' => [
            'Shop' => '/admin/settings',
            'Phones' => '/admin/phones',
            'Banks and UPI' => '/admin/banks',
            'Delivery' => '/admin/delivery',
            'Staff' => '/admin/staff',
        ],
        'settings_more' => [
            'Email log' => '/admin/mail-log',
            'Health' => '/admin/health',
            'Update everything' => '/admin/update',
            'Database update' => '/admin/migrate',
            'Activity log' => '/admin/audit-log',
            'UPI and QR' => '/admin/payments',
        ],
        'help' => [
            'Help' => '/admin/help',
            'Where do I change this?' => '/admin/finder',
        ],
    ];
}

function admin_request_href(): string
{
    $path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/') ?: '/';
    $query = [];
    parse_str((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? ''), $query);
    $keep = [];
    foreach (['status', 'bucket', 'pay', 'filter', 'live'] as $key) {
        if (isset($query[$key]) && (string) $query[$key] !== '') {
            $keep[$key] = (string) $query[$key];
        }
    }
    if ($path === '/admin/product-edit') {
        $path = '/admin/products';
    }
    if ($path === '/admin/inventory' || $path === '/admin/inventory-history') {
        $path = '/admin/stock';
    }
    if ($path === '/admin/payments') {
        return '/admin/payments';
    }
    return $keep ? $path . '?' . http_build_query($keep) : $path;
}

function finder_items(): array
{
    return [
        ['Order calculator in the menu', '/admin/homepage'],
        ['Browse all plants carousel', '/admin/homepage'],
        ['Homepage section order', '/admin/homepage'],
        ['Plant this month', '/admin/months'],
        ['Shop labels English and Marathi', '/admin/labels'],
        ['Change a price', '/admin/prices'],
        ['Bulk offer', '/admin/offers'],
        ['End an offer', '/admin/offers'],
        ['Coupons', '/admin/coupons'],
        ['Price lists', '/admin/price-lists'],
        ['Change stock', '/admin/stock'],
        ['Stock history', '/admin/inventory-history'],
        ['Add a photo', '/admin/photos'],
        ['Add a plant', '/admin/product-edit'],
        ['Hide a plant', '/admin/products'],
        ['Needs attention', '/admin/attention'],
        ['Back in stock requests', '/admin/restock'],
        ['Categories', '/admin/categories'],
        ['Change the discount', '/admin/settings'],
        ['Phone numbers and WhatsApp', '/admin/phones'],
        ['Bank account', '/admin/banks'],
        ['UPI and QR', '/admin/payments'],
        ['Delivery PIN codes', '/admin/delivery'],
        ['Orders', '/admin/orders'],
        ['Payments to confirm', '/admin/orders?status=payment_submitted'],
        ['Orders to pack', '/admin/orders?bucket=pack'],
        ['Invoices and terms', '/admin/invoices'],
        ['Claims', '/admin/claims'],
        ['Customers', '/admin/customers'],
        ['Mark a customer as farmer', '/admin/customers'],
        ['Reviews', '/admin/reviews'],
        ['Messages', '/admin/messages'],
        ['Wholesale', '/admin/wholesale'],
        ['Pages', '/admin/pages'],
        ['Staff', '/admin/staff'],
        ['Update the shop', '/admin/update'],
        ['Help', '/admin/help'],
    ];
}

