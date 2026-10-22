<?php
declare(strict_types=1);

/** Thrown by db() when carriers.db is not present. The front controller turns it into a 503 page. */
class CarriersDbMissing extends RuntimeException
{
}

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
            throw new CarriersDbMissing('carriers.db not found. Upload it to one of the paths listed in app/config.php (db_paths).');
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY, // read-only at the driver level
        ]);
        $pdo->exec('PRAGMA query_only = 1');
        $pdo->exec('PRAGMA case_sensitive_like = 1'); // names are stored upper-case; lets prefix searches use the index
        $pdo->exec('PRAGMA cache_size = -20000');
    }
    return $pdo;
}

/** carriers.db metadata. Returns $default (instead of failing) when carriers.db is missing or unreadable, so footers and forms still render. */
function meta(string $key, ?string $default = null): ?string
{
    static $meta = null;
    if ($meta === null) {
        $meta = [];
        try {
            foreach (db()->query('SELECT key, value FROM meta') as $r) {
                $meta[$r['key']] = $r['value'];
            }
        } catch (CarriersDbMissing $e) {
            $meta = [];
        } catch (PDOException $e) {
            // Damaged or half-uploaded carriers.db: carrier pages fail on their own queries, but footers,
            // /admin, the forms and the legal pages keep working.
            error_log('TDUSA: carriers.db unreadable (' . $e->getMessage() . '); upload it as carriers.new.db and rename when complete');
            $meta = [];
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

/** Adds a column to an existing site.db table when it is missing (older installs). */
function ensure_column(PDO $pdo, string $table, string $column, string $type): void
{
    try {
        $have = [];
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $r) {
            $have[$r['name']] = true;
        }
        if (!isset($have[$column])) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $type);
        }
    } catch (Throwable $e) {
        error_log('TDUSA: could not add ' . $table . '.' . $column . ': ' . $e->getMessage());
    }
}

/** Writable site database for claims, vendor listings, leads and rate limits. */
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
            status TEXT NOT NULL DEFAULT 'pending', -- pending | paid | review | active | rejected | disputed
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
            status TEXT NOT NULL DEFAULT 'pending', -- pending | paid | review | active | rejected | disputed
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
            type TEXT NOT NULL,                -- factoring | insurance | dispatch | ... | suppression | stripe-unmatched
            dot INTEGER, company TEXT, contact_name TEXT, email TEXT, phone TEXT, state TEXT,
            trucks INTEGER, notes TEXT, source_url TEXT,
            status TEXT NOT NULL DEFAULT 'new', -- new | sold | contacted | closed
            ip TEXT, created_at TEXT DEFAULT (datetime('now'))
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            key TEXT PRIMARY KEY,
            n INTEGER NOT NULL DEFAULT 0,
            window_start INTEGER NOT NULL
        )");
        // Columns added after the first release (no-ops on a current schema).
        foreach (['claims', 'vendors'] as $t) {
            ensure_column($pdo, $t, 'user_agent', 'TEXT');
            ensure_column($pdo, $t, 'payment_intent', 'TEXT');
            ensure_column($pdo, $t, 'terms_accepted_at', 'TEXT');
            ensure_column($pdo, $t, 'activated_at', 'TEXT');
        }
        ensure_column($pdo, 'leads', 'user_agent', 'TEXT');
        ensure_column($pdo, 'leads', 'consent_text_version', 'TEXT');
        ensure_column($pdo, 'leads', 'consent_at', 'TEXT');
    }
    return $pdo;
}

/**
 * Fixed-window counter: increments bucket/ident and returns false once more than $max hits
 * landed inside the current window. Fails open if site.db is unavailable (logged).
 */
function rate_ok(string $bucket, string $ident, int $max, int $windowSeconds): bool
{
    try {
        $db = sitedb();
        $key = $bucket . '|' . $ident;
        $now = time();
        $db->beginTransaction();
        $st = $db->prepare('SELECT n, window_start FROM rate_limits WHERE key = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        if (!$row || ($now - (int)$row['window_start']) >= $windowSeconds) {
            $db->prepare('INSERT OR REPLACE INTO rate_limits (key, n, window_start) VALUES (?, 1, ?)')->execute([$key, $now]);
            $n = 1;
        } else {
            $n = (int)$row['n'] + 1;
            $db->prepare('UPDATE rate_limits SET n = ? WHERE key = ?')->execute([$n, $key]);
        }
        $db->commit();
        if (mt_rand(1, 50) === 1) {
            // Occasional prune of windows that ended more than two days ago.
            $db->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 2 * 86400]);
        }
        return $n <= $max;
    } catch (Throwable $e) {
        error_log('TDUSA rate_ok: ' . $e->getMessage());
        try {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Throwable $e2) {
            // nothing more to do; fail open
        }
        return true;
    }
}

/** Hits recorded for bucket/ident in the current window, without counting a new one. */
function rate_hits(string $bucket, string $ident, int $windowSeconds): int
{
    try {
        $st = sitedb()->prepare('SELECT n, window_start FROM rate_limits WHERE key = ?');
        $st->execute([$bucket . '|' . $ident]);
        $row = $st->fetch();
        if (!$row || (time() - (int)$row['window_start']) >= $windowSeconds) {
            return 0;
        }
        return (int)$row['n'];
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * SQL condition for a claim or vendor row that is shown publicly: set ACTIVE by the owner in /admin
 * and activated less than a year ago. Listings run one year (see /terms) and drop off automatically
 * unless renewed; a renewal is a new order, which creates a new row with its own activation date.
 */
const LIVE_LISTING_SQL = "status = 'active' AND COALESCE(activated_at, paid_at, created_at) >= datetime('now', '-365 days')";

/** ACTIVE (paid and reviewed) claim for a carrier, or null. 'paid' rows are awaiting review and stay private. */
function carrier_claim(int $dot): ?array
{
    try {
        $st = sitedb()->prepare('SELECT * FROM claims WHERE dot = ? AND ' . LIVE_LISTING_SQL . ' ORDER BY id DESC LIMIT 1');
        $st->execute([$dot]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

/** USDOT numbers with an ACTIVE featured claim (cached per request). Used to pin them on city/cargo pages. */
function featured_claim_dots(): array
{
    static $dots = null;
    if ($dots === null) {
        $dots = [];
        try {
            foreach (sitedb()->query('SELECT DISTINCT dot FROM claims WHERE ' . LIVE_LISTING_SQL . ' AND plan = \'featured\'') as $r) {
                $dots[] = (int)$r['dot'];
            }
        } catch (Throwable $e) {
            $dots = [];
        }
    }
    return $dots;
}

/** ACTIVE vendor listings for a category and state (featured first). */
function vendors_for(string $category, ?string $state, int $limit = 50): array
{
    try {
        $sql = 'SELECT * FROM vendors WHERE category = ? AND ' . LIVE_LISTING_SQL;
        $params = [$category];
        if ($state) {
            $sql .= " AND (states_served = 'ALL' OR (',' || states_served || ',') LIKE ? OR state = ?)";
            $params[] = '%,' . $state . ',%';
            $params[] = $state;
        }
        $sql .= ' ORDER BY (plan = \'featured\') DESC, name LIMIT ' . (int)$limit;
        $st = sitedb()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * [category => [state abbr => true]] for every category x state page that has at least one
 * ACTIVE vendor (states_served 'ALL' counts for every state; the home state counts too, as in
 * vendors_for()). Used by the sitemap so empty category x state pages are never listed.
 */
function active_vendor_states(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    try {
        $rows = sitedb()->query("SELECT category, state, states_served FROM vendors WHERE " . LIVE_LISTING_SQL)->fetchAll();
    } catch (Throwable $e) {
        return $map;
    }
    foreach ($rows as $v) {
        $cat = (string)$v['category'];
        $served = strtoupper((string)$v['states_served']);
        $states = [];
        if ($served === 'ALL' || str_contains(',' . $served . ',', ',ALL,')) {
            $states = array_keys(US_STATES);
        } else {
            foreach (explode(',', $served) as $s) {
                $s = trim($s);
                if (isset(US_STATES[$s])) {
                    $states[] = $s;
                }
            }
        }
        $home = strtoupper(trim((string)$v['state']));
        if (isset(US_STATES[$home])) {
            $states[] = $home;
        }
        foreach ($states as $s) {
            $map[$cat][$s] = true;
        }
    }
    return $map;
}

/** ACTIVE featured vendors, any category, that serve $state (for the rotating sponsor slots). */
function featured_vendors_for_state(string $state): array
{
    try {
        $st = sitedb()->prepare("SELECT * FROM vendors WHERE " . LIVE_LISTING_SQL . " AND plan = 'featured'
            AND (states_served = 'ALL' OR (',' || states_served || ',') LIKE ? OR state = ?) ORDER BY id");
        $st->execute(['%,' . $state . ',%', $state]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
