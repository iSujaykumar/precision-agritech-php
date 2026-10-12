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

function desk_allows(string $role, string $need): bool
{
    $rank = ['staff' => 1, 'manager' => 2, 'owner' => 3];
    $have = $rank[$role] ?? 3;
    return $have >= ($rank[$need] ?? 3);
}

function require_desk(array $admin, string $need): void
{
    $role = (string) ($admin['desk_role'] ?? 'owner');
    if (desk_allows($role, $need)) {
        return;
    }
    http_response_code(403);
    admin_open('Not allowed');
    echo '<p class="flash bad" role="alert">That page needs a higher desk role.</p>';
    admin_close();
    exit;
}

function csv_cell(string $value): string
{
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$key, $value]);
}

function checked_setting(string $key, string $value): string
{
    $value = trim($value);
    $money = ['shipping_flat_inr', 'free_shipping_over_inr', 'min_order_inr', 'cod_min_inr', 'cod_max_inr', 'max_unpaid_phone', 'max_unpaid_ip', 'max_trays_phone', 'cod_fail_limit', 'reservation_hours_bank', 'reservation_hours_cod', 'claim_hours'];
    if (in_array($key, $money, true)) {
        if ($value === '' || !preg_match('/^\d+$/', $value) || (int) $value > 1000000) {
            throw new RuntimeException('Enter a whole number up to 1000000 for ' . $key . '.');
        }
        return (string) (int) $value;
    }
    if (in_array($key, ['shop_email', 'mail_from', 'grievance_officer_email'], true)) {
        if ($value === '' && $key === 'grievance_officer_email') {
            return '';
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email for ' . $key . '.');
        }
        return $value;
    }
    if ($key === 'map_url') {
        if ($value !== '' && !str_starts_with($value, 'https://')) {
            throw new RuntimeException('The map link must start with https://.');
        }
        return $value;
    }
    if ($key === 'whatsapp_number') {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        if (!preg_match('/^91[6-9][0-9]{9}$/', $digits)) {
            throw new RuntimeException('Enter a 10-digit WhatsApp number.');
        }
        return $digits;
    }
    if ($key === 'upi_id') {
        if ($value !== '' && !preg_match('/^[A-Za-z0-9.\-_]{2,40}@[A-Za-z]{2,20}$/', $value)) {
            throw new RuntimeException('Enter a UPI ID like name@bank.');
        }
        return $value;
    }
    if ($key === 'gstin') {
        $value = strtoupper($value);
        if ($value !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $value)) {
            throw new RuntimeException('GSTIN must be 15 characters.');
        }
        return $value;
    }
    if ($key === 'gst_rate') {
        if ($value === '') {
            return '';
        }
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 28) {
            throw new RuntimeException('GST rate must be between 0 and 28.');
        }
        return (string) (0 + $value);
    }
    if ($key === 'bank_ifsc') {
        $value = strtoupper($value);
        if ($value !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $value)) {
            throw new RuntimeException('IFSC does not look right.');
        }
        return $value;
    }
    if (in_array($key, ['delivery_pins', 'zone_shipping'], true)) {
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $pinOk = (bool) preg_match('/^[0-9]{6}(\s*-\s*[0-9]{6})?$/', $line);
            $zoneOk = (bool) preg_match('/^[0-9]{6}(\s*-\s*[0-9]{6})?\s*=\s*[0-9]+$/', $line);
            if ($key === 'delivery_pins' && !$pinOk) {
                throw new RuntimeException('Each PIN line must be 6 digits, or 6 digits, a dash, then 6 digits.');
            }
            if ($key === 'zone_shipping' && !$zoneOk) {
                throw new RuntimeException('Each shipping line must look like 412110=180 or 411001-411048=220.');
            }
        }
        return $value;
    }
    return $value;
}

function desk_warnings(): array
{
    global $config;
    $warnings = [];
    $cron = strtotime((string) setting('cron_last_run', ''));
    if (!$cron || (time() - $cron) > 1800) {
        $warnings[] = 'The reservation cleanup has not run in the last 30 minutes.';
    }
    if (!empty($config['allow_setup'])) {
        $warnings[] = 'Setup is still open.';
    }
    if (!empty($config['debug'])) {
        $warnings[] = 'Debug is on.';
    }
    if (!empty($config['show_otp_on_screen'])) {
        $warnings[] = 'Sign-in codes are shown on screen.';
    }
    if (trim(setting('upi_id', '')) === '' || !upi_id_ok(setting('upi_id', ''))) {
        $warnings[] = 'The UPI ID is missing or not valid.';
    }
    if (trim(setting('delivery_pins', '')) === '') {
        $warnings[] = 'The delivery PIN list is empty.';
    }
    $dir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($dir) || !is_writable($dir)) {
        $warnings[] = 'The uploads folder is not writable.';
    }
    if (!function_exists('imagewebp')) {
        $warnings[] = 'WebP support is missing.';
    }
    try {
        $failed = (int) db()->query('SELECT COUNT(*) FROM mail_log WHERE ok = 0 AND created_at > (NOW() - INTERVAL 1 DAY)')->fetchColumn();
        if ($failed > 3) {
            $warnings[] = $failed . ' emails failed in the last 24 hours.';
        }
    } catch (Throwable $err) {
        $warnings[] = 'The email log is not ready.';
    }
    try {
        $late = (int) db()->query("SELECT COUNT(*) FROM orders o WHERE o.status = 'payment_submitted' AND EXISTS (SELECT 1 FROM order_events e WHERE e.order_id = o.id AND e.status = 'payment_submitted' AND e.created_at < (NOW() - INTERVAL 6 HOUR))")->fetchColumn();
        if ($late > 0) {
            $warnings[] = $late . ' payment' . ($late === 1 ? '' : 's') . ' waiting to be checked for more than 6 hours.';
        }
    } catch (Throwable $err) {
        $warnings[] = 'Payment checks could not be counted.';
    }
    return $warnings;
}

function desk_counts(): array
{
    static $counts = null;
    if ($counts !== null) {
        return $counts;
    }
    $orders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('placed','payment_pending','payment_submitted')")->fetchColumn();
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
        'payment_pending' => 'Waiting for payment',
        'payment_submitted' => 'Payment to check',
        'payment_confirmed' => 'Paid',
        'confirmed' => 'Confirmed',
        'packed' => 'Packed',
        'preparing' => 'Preparing',
        'out_for_delivery' => 'Out for delivery',
        'dispatched' => 'Dispatched',
        'delivery_failed' => 'Delivery failed',
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
    if (preg_match('#^(/uploads/products/[a-z0-9]+)\.webp$#', $url, $m)) {
        foreach ([400, 800, 1200] as $width) {
            $variant = dirname(__DIR__) . $m[1] . '-' . $width . '.webp';
            if (is_file($variant)) {
                unlink($variant);
            }
        }
    }
}

function upload_error_message(int $error): ?string
{
    return match ($error) {
        UPLOAD_ERR_OK => null,
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is too big. Use a file under 5 MB.',
        UPLOAD_ERR_PARTIAL => 'The upload stopped part way. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose an image.',
        default => 'That image could not be uploaded.',
    };
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    $bytes = match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
    return (int) $bytes;
}

function assert_image_safe(string $tmp): array
{
    $info = @getimagesize($tmp);
    if (!$info) {
        throw new RuntimeException('Use a JPEG, PNG or WebP image.');
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width > 6000 || $height > 6000 || ($width * $height) > 24000000) {
        throw new RuntimeException('That image is too large to open. Use a smaller photo.');
    }
    $need = $width * $height * 5;
    $limit = ini_bytes((string) ini_get('memory_limit'));
    if ($limit > 0 && (memory_get_usage(true) + $need) > (int) ($limit * 0.7)) {
        throw new RuntimeException('That image is too large to open. Use a smaller photo.');
    }
    return [$width, $height, (string) ($info['mime'] ?? '')];
}

function write_webp_scaled($src, string $dest, int $maxW, int $quality): void
{
    $width = imagesx($src);
    $height = imagesy($src);
    $targetW = min($maxW, $width);
    $targetH = max(1, (int) round($height * ($targetW / max(1, $width))));
    $dst = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
    $ok = imagewebp($dst, $dest, $quality);
    imagedestroy($dst);
    if (!$ok) {
        throw new RuntimeException('The image could not be saved.');
    }
}

function save_upload_webp(array $file, string $folder, int $minW, int $minH): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $message = upload_error_message($error);
    if ($message !== null && $error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($message);
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('That image is too big. Use a file under 5 MB.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        throw new RuntimeException('Choose an image.');
    }
    [$width, $height] = assert_image_safe($tmp);
    if ($width < $minW || $height < $minH) {
        throw new RuntimeException('Use a larger image.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    if (!is_string($mime) || !isset($loaders[$mime]) || !function_exists('imagewebp') || !function_exists($loaders[$mime])) {
        throw new RuntimeException('Use a JPEG, PNG or WebP image.');
    }
    try {
        $src = @$loaders[$mime]($tmp);
    } catch (Throwable $err) {
        $src = false;
    }
    if (!$src) {
        throw new RuntimeException('Use a JPEG, PNG or WebP image.');
    }
    $dir = dirname(__DIR__) . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        throw new RuntimeException('The image could not be saved.');
    }
    $stem = bin2hex(random_bytes(8));
    try {
        write_webp_scaled($src, $dir . '/' . $stem . '.webp', 1600, 82);
        if ($folder === '/uploads/products') {
            foreach ([400 => 78, 800 => 78, 1200 => 78] as $width => $quality) {
                write_webp_scaled($src, $dir . '/' . $stem . '-' . $width . '.webp', $width, $quality);
            }
        }
    } catch (Throwable $err) {
        imagedestroy($src);
        throw new RuntimeException('The image could not be saved.');
    }
    imagedestroy($src);
    return $folder . '/' . $stem . '.webp';
}

function save_product_image(array $file): string
{
    return save_upload_webp($file, '/uploads/products', 600, 450);
}

function notify_mail(string $to, string $subject, string $body): bool
{
    return send_mail($to, $subject, $body);
}

function mail_order_customer(array $order, string $intro): void
{
    $email = trim((string) ($order['customer_email'] ?? ''));
    if ($email === '') {
        return;
    }
    $lines = $intro . "\n\nOrder " . ($order['order_number'] ?? '') . "\nTotal " . inr((int) ($order['total_inr'] ?? 0)) . "\n";
    if (!empty($order['reserved_until'])) {
        $lines .= 'Reserved until ' . fmt_when((string) $order['reserved_until']) . "\n";
    }
    $method = (string) ($order['payment_method'] ?? '');
    if (in_array($method, ['upi', 'bank_transfer'], true) && ($order['payment_status'] ?? '') !== 'paid') {
        $lines .= "\n" . bank_instructions((string) $order['order_number'], (int) $order['total_inr']) . "\n";
        $token = (string) ($order['lookup_token'] ?? '');
        global $config;
        $base = rtrim((string) ($config['site_url'] ?? ''), '/');
        if ($token !== '' && $base !== '') {
            $lines .= "Order page: " . $base . "/order/" . $token . "\n";
            $lines .= "The QR code is on that page. This email does not include a payment image.\n";
        }
    }
    notify_mail($email, 'Order ' . ($order['order_number'] ?? '') . ' — Precision Agritech', $lines);
}

function mail_staff(string $subject, string $body): void
{
    notify_mail(shop_setting('shop_email', 'info@precisionagritech.in'), $subject, $body);
}
