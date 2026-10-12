<?php
declare(strict_types=1);

function shop_upgrade_statements(string $sql): array
{
    $sql = preg_replace('/DELIMITER\s+\$\$\s*.*?DELIMITER\s*;/s', "\n", $sql) ?? $sql;
    $statements = [];
    $buf = '';
    $quote = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($ch === "'") {
            if ($quote && $i + 1 < $len && $sql[$i + 1] === "'") {
                $buf .= "''";
                $i++;
                continue;
            }
            $quote = !$quote;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';' && !$quote) {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $tail = trim($buf);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function shop_upgrade_args(string $stmt): array
{
    $open = strpos($stmt, '(');
    if ($open === false) {
        throw new RuntimeException('The upgrade file has a broken procedure call.');
    }
    $args = [];
    $i = $open + 1;
    $len = strlen($stmt);
    while ($i < $len) {
        while ($i < $len && ctype_space($stmt[$i])) {
            $i++;
        }
        if ($i >= $len || $stmt[$i] === ')') {
            break;
        }
        if ($stmt[$i] !== "'") {
            throw new RuntimeException('The upgrade file has a broken procedure call.');
        }
        $i++;
        $value = '';
        while ($i < $len) {
            if ($stmt[$i] === "'" && $i + 1 < $len && $stmt[$i + 1] === "'") {
                $value .= "'";
                $i += 2;
                continue;
            }
            if ($stmt[$i] === "'") {
                $i++;
                break;
            }
            $value .= $stmt[$i];
            $i++;
        }
        $args[] = $value;
        while ($i < $len && ctype_space($stmt[$i])) {
            $i++;
        }
        if ($i < $len && $stmt[$i] === ',') {
            $i++;
        }
    }
    return $args;
}

function shop_upgrade(PDO $pdo): array
{
    $path = dirname(__DIR__) . '/database/upgrade.sql';
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('The upgrade file could not be read.');
    }
    $log = [];
    foreach (shop_upgrade_statements($sql) as $stmt) {
        $plain = trim(preg_replace('/^\s*--.*$/m', '', $stmt) ?? '');
        if ($plain === '' || str_starts_with($plain, '--')) {
            continue;
        }
        if (preg_match('/^DROP\s+PROCEDURE\b/i', $plain)) {
            continue;
        }
        if (preg_match('/^CALL\s+(pa_add_column|pa_add_index)\s*\(/i', $plain, $match)) {
            $args = shop_upgrade_args($plain);
            if (count($args) !== 3) {
                throw new RuntimeException('The upgrade file has a broken procedure call.');
            }
            [$table, $name, $ddl] = $args;
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                throw new RuntimeException('The upgrade file has an unexpected name.');
            }
            if (strcasecmp($match[1], 'pa_add_column') === 0) {
                $check = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $check->execute([$table, $name]);
                if ($check->fetch()) {
                    $log[] = 'column exists ' . $table . '.' . $name;
                    continue;
                }
            } else {
                $check = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
                $check->execute([$table, $name]);
                if ($check->fetch()) {
                    $log[] = 'index exists ' . $table . '.' . $name;
                    continue;
                }
            }
            try {
                $pdo->exec($ddl);
            } catch (PDOException $err) {
                error_log($err->getMessage());
                throw new RuntimeException('The database update stopped at ' . $table . '.' . $name . '.');
            }
            $log[] = 'added ' . $table . '.' . $name;
            continue;
        }
        try {
            $pdo->exec($plain);
        } catch (PDOException $err) {
            error_log($err->getMessage());
            throw new RuntimeException('The database update stopped at: ' . strtok($plain, " \n"));
        }
        $log[] = 'ran ' . strtok($plain, " \n");
    }
    return $log;
}
