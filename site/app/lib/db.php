<?php
declare(strict_types=1);

/** Read-only connection to carriers.db (built by tools/build_carriers_db.py). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = null;
        foreach ((array)cfg('db_paths') as $p) {
            if (is_file($p)) {
                $path = $p;
                break;
            }
        }
        if ($path === null) {
            http_response_code(503);
            header('Content-Type: text/plain');
            echo "carriers.db not found. Upload it to one of the paths listed in app/config.php (db_paths).";
            exit;
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA query_only = 1');
        $pdo->exec('PRAGMA case_sensitive_like = 1'); // names are stored upper-case; lets prefix searches use the index
        $pdo->exec('PRAGMA cache_size = -20000');
    }
    return $pdo;
}

function meta(string $key, ?string $default = null): ?string
{
    static $meta = null;
    if ($meta === null) {
        $meta = [];
        foreach (db()->query('SELECT key, value FROM meta') as $r) {
            $meta[$r['key']] = $r['value'];
        }
    }
    return $meta[$key] ?? $default;
}

function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q1(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function qv(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/** Writable site database for claims, vendor listings and leads. */
function sitedb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = (string)cfg('site_db_path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec("CREATE TABLE IF NOT EXISTS claims (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dot INTEGER NOT NULL,
            plan TEXT NOT NULL,                -- verified | featured
            status TEXT NOT NULL DEFAULT 'pending', -- pending | paid | active | rejected
            contact_name TEXT, contact_role TEXT, email TEXT, phone TEXT, website TEXT,
            public_email INTEGER DEFAULT 0,    -- show the email on the public page?
            notes TEXT,
            stripe_session TEXT, amount_cents INTEGER, paid_at TEXT,
            ip TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_claims_dot ON claims(dot, status)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS vendors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,            -- e.g. factoring_companies
            plan TEXT NOT NULL,                -- listed | featured
            status TEXT NOT NULL DEFAULT 'pending', -- pending | paid | active | rejected
            name TEXT NOT NULL, contact_name TEXT, email TEXT, phone TEXT, website TEXT,
            city TEXT, state TEXT,             -- home state (2 letters)
            states_served TEXT,                -- comma list of 2-letter states or ALL
            blurb TEXT,
            stripe_session TEXT, amount_cents INTEGER, paid_at TEXT,
            ip TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vendors_cat ON vendors(category, status)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,                -- factoring | insurance | dispatch | ... | advertiser
            dot INTEGER, company TEXT, contact_name TEXT, email TEXT, phone TEXT, state TEXT,
            trucks INTEGER, notes TEXT, source_url TEXT,
            status TEXT NOT NULL DEFAULT 'new', -- new | sold | contacted | closed
            ip TEXT, created_at TEXT DEFAULT (datetime('now'))
        )");
    }
    return $pdo;
}

/** Active (paid) claim for a carrier, or null. */
function carrier_claim(int $dot): ?array
{
    try {
        $st = sitedb()->prepare('SELECT * FROM claims WHERE dot = ? AND status IN (\'paid\',\'active\') ORDER BY id DESC LIMIT 1');
        $st->execute([$dot]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

/** Active vendor listings for a category and state (featured first). */
function vendors_for(string $category, ?string $state, int $limit = 50): array
{
    try {
        $sql = 'SELECT * FROM vendors WHERE category = ? AND status IN (\'paid\',\'active\')';
        $params = [$category];
        if ($state) {
            $sql .= " AND (states_served = 'ALL' OR (',' || states_served || ',') LIKE ? OR state = ?)";
            $params[] = '%,' . $state . ',%';
            $params[] = $state;
        }
        $sql .= ' ORDER BY (plan = "featured") DESC, name LIMIT ' . (int)$limit;
        $st = sitedb()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
