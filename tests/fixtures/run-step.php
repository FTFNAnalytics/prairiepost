<?php
/**
 * TEST SUPPORT ONLY: execute one migration step body directly, standing in
 * for an operator completing an interrupted step "by hand" from the
 * definition the runner printed. Lives under tests/, not tools/ — it is
 * not an operational interface and skips every runner safeguard.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$v = (int) ($argv[1] ?? 0);
$steps = pp_migrations();
if (!isset($steps[$v])) {
    fwrite(STDERR, "no step $v\n");
    exit(2);
}
$steps[$v](pp_db_connect(), pp_db_driver());
echo "step $v body executed\n";
