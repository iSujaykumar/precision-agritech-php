<?php
declare(strict_types=1);

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

