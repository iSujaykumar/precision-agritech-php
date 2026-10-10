<?php
declare(strict_types=1);

function flash_set(string $message, bool $ok = true): void
{
    $_SESSION['desk_flash'] = [$ok ? 'ok' : 'bad', $message];
}

function flash_take(): ?array
{
    $flash = $_SESSION['desk_flash'] ?? null;
    unset($_SESSION['desk_flash']);
    if (!is_array($flash) || count($flash) < 2) {
        return null;
    }
    return ['kind' => (string) $flash[0], 'message' => (string) $flash[1]];
}

function slugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function renumber_categories(): void
{
    $ids = db()->query('SELECT id FROM categories ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
    $stmt = db()->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
    $n = 1;
    foreach ($ids as $id) {
        $stmt->execute([$n, (int) $id]);
        $n++;
    }
}

function coupon_in_window(array $coupon): bool
{
    $now = time();
    if (!empty($coupon['starts_at']) && strtotime((string) $coupon['starts_at']) > $now) {
        return false;
    }
    if (!empty($coupon['ends_at']) && strtotime((string) $coupon['ends_at']) < $now) {
        return false;
    }
    return true;
}

function assert_coupon_window(array $coupon): void
{
    if (!coupon_in_window($coupon)) {
        throw new RuntimeException('This coupon is not valid right now.');
    }
}

function shop_setting(string $key, string $fallback = ''): string
{
    try {
        return setting($key, $fallback);
    } catch (Throwable $err) {
        return $fallback;
    }
}

function shop_paused(): bool
{
    return shop_setting('shop_paused', '0') === '1';
}

function whatsapp_digits(): string
{
    return preg_replace('/\D+/', '', shop_setting('whatsapp_number', '919011975959')) ?? '';
}

function wa_href(string $text): string
{
    $digits = whatsapp_digits();
    if ($digits === '') {
        return '';
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function shop_phone_digits(): string
{
    $digits = preg_replace('/\D+/', '', shop_setting('shop_phone', '9011975959')) ?? '';
    return $digits !== '' ? $digits : '9011975959';
}

function public_reviewer(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = (string) ($parts[0] ?? 'Customer');
    $last = (string) ($parts[1] ?? '');
    if ($last !== '') {
        return $first . ' ' . strtoupper(substr($last, 0, 1)) . '.';
    }
    return $first;
}

function stars_markup(float $rating, int $count = 0): string
{
    $full = max(0, min(5, (int) round($rating)));
    $label = number_format($rating, 1) . ' out of 5';
    if ($count > 0) {
        $label .= ', ' . $count . ' reviews';
    }
    return '<span class="stars" aria-label="' . e($label) . '">' . str_repeat('★', $full) . str_repeat('☆', 5 - $full) . '</span>';
}

function product_rating(int $productId): ?array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (db()->query("SELECT product_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS n FROM reviews WHERE status = 'approved' AND product_id IS NOT NULL GROUP BY product_id") as $row) {
                $map[(int) $row['product_id']] = ['avg' => (float) $row['avg_rating'], 'n' => (int) $row['n']];
            }
        } catch (Throwable $err) {
            $map = [];
        }
    }
    $row = $map[$productId] ?? null;
    return ($row && $row['n'] > 0) ? $row : null;
}

function clean_review_body(string $body): string
{
    $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
    $body = preg_replace('#https?://\S+|www\.\S+#i', '', $body) ?? '';
    $body = str_replace(['<', '>'], '', $body);
    return trim($body);
}

function review_limit_hit(int $userId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() >= 3;
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$key, $value]);
}

function desk_counts(): array
{
    static $counts = null;
    if ($counts !== null) {
        return $counts;
    }
    $orders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending')")->fetchColumn();
    $reviews = (int) db()->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
    $messages = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
    $wholesale = (int) db()->query("SELECT COUNT(*) FROM wholesale_requests WHERE status = 'new'")->fetchColumn();
    $counts = ['orders' => $orders, 'reviews' => $reviews, 'enquiries' => $messages + $wholesale];
    return $counts;
}

function order_label(string $status): string
{
    return [
        'placed' => 'New',
        'payment_pending' => 'Payment pending',
        'payment_confirmed' => 'Paid',
        'preparing' => 'Preparing',
        'dispatched' => 'Dispatched',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ][$status] ?? $status;
}

function pager_nav(int $page, int $pages): void
{
    if ($pages < 2) {
        return;
    }
    echo '<nav class="pager" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) {
        $query = $_GET;
        $query['page'] = $i;
        $href = '?' . http_build_query($query);
        if ($i === $page) {
            echo '<span class="is-current">' . $i . '</span>';
        } else {
            echo '<a href="' . e($href) . '">' . $i . '</a>';
        }
    }
    echo '</nav>';
}

function list_bounds(int $total, int $perPage = 25): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    return [$page, $pages, ($page - 1) * $perPage];
}

function remove_managed_image(?string $url): void
{
    $url = (string) $url;
    if (!str_starts_with($url, '/uploads/products/')) {
        return;
    }
    $path = dirname(__DIR__) . $url;
    if (is_file($path)) {
        unlink($path);
    }
}

function save_product_image(array $file): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose an image.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Images must be 5 MB or smaller.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('That image could not be uploaded.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Images must be 5 MB or smaller.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    if (!is_string($mime) || !isset($loaders[$mime])) {
        throw new RuntimeException('Use a JPEG, PNG or WebP image.');
    }
    $info = @getimagesize($tmp);
    if (!$info || (int) $info[0] < 600 || (int) $info[1] < 450) {
        throw new RuntimeException('Use an image at least 600 by 450 pixels.');
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('The image could not be saved. Please try again.');
    }
    $src = $loaders[$mime]($tmp);
    if (!$src) {
        throw new RuntimeException('Use a JPEG, PNG or WebP image.');
    }
    $width = imagesx($src);
    $height = imagesy($src);
    if ($width > 1600) {
        $nextH = (int) round($height * (1600 / $width));
        $dst = imagecreatetruecolor(1600, $nextH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, 1600, $nextH, $width, $height);
        imagedestroy($src);
        $src = $dst;
    }
    $dir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        throw new RuntimeException('The image could not be saved.');
    }
    $name = bin2hex(random_bytes(8)) . '.webp';
    $ok = imagewebp($src, $dir . '/' . $name, 82);
    imagedestroy($src);
    if (!$ok) {
        throw new RuntimeException('The image could not be saved.');
    }
    return '/uploads/products/' . $name;
}

function notify_mail(string $to, string $subject, string $body): void
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $from = shop_setting('mail_from', 'info@precisionagritech.in');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'info@precisionagritech.in';
    }
    $headers = 'From: Precision Agritech <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
    try {
        @mail($to, $subject, $body, $headers);
    } catch (Throwable $err) {
        error_log('mail failed');
    }
}

function mail_order_customer(array $order, string $intro): void
{
    $lines = $intro . "\n\nOrder " . $order['order_number'] . "\nTotal " . inr((int) $order['total_inr']) . "\n";
    $pay = trim(shop_setting('payment_instructions', ''));
    if ($pay !== '') {
        $lines .= "\n" . $pay . "\n";
    }
    notify_mail((string) $order['customer_email'], 'Order ' . $order['order_number'] . ' — Precision Agritech', $lines);
}

function mail_staff(string $subject, string $body): void
{
    notify_mail(shop_setting('shop_email', 'info@precisionagritech.in'), $subject, $body);
}
