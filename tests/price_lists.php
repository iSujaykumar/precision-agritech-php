<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'off';
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/migrate.php';

$fail = 0;
function expect(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo "PASS $name\n";
        return;
    }
    $fail++;
    echo "FAIL $name" . ($detail !== '' ? " $detail" : '') . "\n";
}

$cases = [310 => 46500, 325 => 48750, 375 => 56250, 425 => 63750, 475 => 71250, 525 => 78750, 575 => 86250];
foreach ($cases as $paise => $tray) {
    expect('exact tray ' . $paise, exact_tray_paise($paise, 150) === $tray, (string) exact_tray_paise($paise, 150));
}
expect('half rupee words', amount_paise_words(63750) === 'Six hundred thirty seven rupees and 50 paise only');

$root = dirname(__DIR__);
$oldHits = [];
$banned = '9011' . '975959';
$bannedWa = '91' . $banned;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (!preg_match('/\.(php|js|sql|txt|html|json)$/', $path)) {
        continue;
    }
    $text = file_get_contents($path);
    if ($text !== false && (str_contains($text, $banned) || str_contains($text, $bannedWa))) {
        $oldHits[] = str_replace($root . '/', '', $path);
    }
}
expect('old phone removed from pages', $oldHits === [], implode(',', $oldHits));

$pdo = db();
$log = shop_upgrade($pdo);
expect('upgrade runs', is_array($log));
$again = shop_upgrade($pdo);
expect('upgrade runs twice', is_array($again));

$pdo->beginTransaction();
try {
    $loaded = load_supplied_lists($pdo);
    load_business_settings($pdo);
    expect('67 seasonal rows', (int) $pdo->query("SELECT COUNT(*) FROM price_list_items i JOIN price_lists l ON l.id=i.price_list_id WHERE l.slug='seasonal-2026-27'")->fetchColumn() === 67);
    expect('33 farmer rows', (int) $pdo->query("SELECT COUNT(*) FROM price_list_items i JOIN price_lists l ON l.id=i.price_list_id WHERE l.slug='farmer'")->fetchColumn() === 33);
    expect('19 matched parents', $loaded['matched_parents'] === 19, (string) $loaded['matched_parents']);
    expect('45 sheet groups', $loaded['groups'] === 45, (string) $loaded['groups']);
    $cards = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE active=1 AND farmer_only=0 AND list_only=0 AND parent_product_id IS NULL')->fetchColumn();
    expect('30 shop cards', $cards === 30, (string) $cards);
    $ptilotus = (int) $pdo->query("SELECT price_inr FROM products WHERE slug='ptilotus'")->fetchColumn();
    expect('ptilotus price unchanged', $ptilotus === 450, (string) $ptilotus);
    $marigold = $pdo->query("SELECT price_per_seedling_paise, tray_paise, price_inr FROM products WHERE slug='marigold'")->fetch();
    expect('marigold seedling 425', (int) $marigold['price_per_seedling_paise'] === 425);
    expect('marigold tray paise 63750', (int) $marigold['tray_paise'] === 63750, (string) $marigold['tray_paise']);
    $activeChild = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE parent_product_id IS NOT NULL AND farmer_only=0 AND active=1')->fetchColumn();
    expect('variety stays on the parent card', $activeChild === 0, (string) $activeChild);
    $farmerSlug = (string) $pdo->query("SELECT slug FROM products WHERE farmer_only=1 AND parent_product_id IS NOT NULL LIMIT 1")->fetchColumn();
    expect('farmer hidden from guest url', product_by_slug($farmerSlug) === null);
    $marathi = (string) $pdo->query("SELECT marathi_name FROM products WHERE variety='Mahavishnu Yellow' AND farmer_only=1")->fetchColumn();
    expect('marathi round trip', $marathi === 'महाविष्णू येलो', $marathi);
    $loadedAgain = load_supplied_lists($pdo);
    $createdAgain = 0;
    foreach ($loadedAgain['rows'] as $row) {
        if (str_starts_with((string) $row['action'], 'created')) {
            $createdAgain++;
        }
    }
    expect('second import creates nothing', $createdAgain === 0, (string) $createdAgain);
    expect('three phones', (int) $pdo->query('SELECT COUNT(*) FROM phone_numbers')->fetchColumn() === 3);
    expect('banks stay unverified', (int) $pdo->query('SELECT COUNT(*) FROM bank_accounts WHERE verified=1 OR show_public=1')->fetchColumn() === 0);
    expect('three draft banks', (int) $pdo->query('SELECT COUNT(*) FROM bank_accounts')->fetchColumn() === 3);
    expect('public banks hidden', public_bank_accounts() === []);
    $_SESSION['wa_digits'] = '';
    $first = whatsapp_digits();
    $second = whatsapp_digits();
    expect('whatsapp sticky', $first !== '' && $first === $second, $first);
    expect('whatsapp is one of three', in_array($first, ['918080370152', '918421577787', '918421577725'], true), $first);
    save_setting('whatsapp_mode', 'fixed');
    save_setting('whatsapp_fixed_id', (string) $pdo->query("SELECT id FROM phone_numbers WHERE digits='8421577787'")->fetchColumn());
    unset($_SESSION['wa_digits']);
    expect('fixed whatsapp', whatsapp_digits_for('fixed') === '918421577787');
    $pdo->exec('UPDATE phone_numbers SET whatsapp_enabled=0');
    unset($_SESSION['wa_digits']);
    expect('no whatsapp when all disabled', whatsapp_digits() === '');
    $pdo->exec('UPDATE phone_numbers SET whatsapp_enabled=1');
    expect('schema ready', schema_ready());
    $pdo->rollBack();
} catch (Throwable $err) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    expect('import transaction', false, $err->getMessage());
}

echo $fail === 0 ? "ALL PASS\n" : "FAILURES $fail\n";
exit($fail === 0 ? 0 : 1);
