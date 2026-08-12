<?php
/** The sign-in throttle, against an in-memory SQLite. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function current_site_id(): int
{
    return 1;
}
function pp_like(): string
{
    return 'LIKE';
}
require dirname(__DIR__) . '/app/db.php';
require dirname(__DIR__) . '/app/models.php';

foreach (pp_schema_ddl('sqlite') as $sql) {
    db()->exec($sql);
}
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

ok(!pp_login_blocked('a@b.ca'), 'clean slate not blocked');
for ($i = 0; $i < 5; $i++) {
    pp_login_record('a@b.ca', false);
}
ok(!pp_login_blocked('a@b.ca'), 'five failures still allowed');
pp_login_record('a@b.ca', false);
ok(pp_login_blocked('a@b.ca'), 'six failures block the account');
ok(!pp_login_blocked('other@b.ca'), 'another account unaffected (IP under 20)');

// Successes never count against anyone.
for ($i = 0; $i < 10; $i++) {
    pp_login_record('c@d.ca', true);
}
ok(!pp_login_blocked('c@d.ca'), 'successes are not failures');

// The per-address ceiling: 20 failures across many accounts blocks the IP.
for ($i = 0; $i < 14; $i++) {
    pp_login_record("bot$i@spam.ca", false);
}
ok(pp_login_blocked('fresh@b.ca'), 'twenty failures from one address block it for any email');

// Old failures age out of the window.
db()->exec("UPDATE login_attempts SET created_at = '2020-01-01 00:00:00'");
ok(!pp_login_blocked('a@b.ca'), 'the window expires');

exit($fails ? 1 : 0);
