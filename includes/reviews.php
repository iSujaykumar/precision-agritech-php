<?php
declare(strict_types=1);

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

