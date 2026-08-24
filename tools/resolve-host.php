<?php
/**
 * Which paper answers a hostname, and which layer decided.
 *
 *     php tools/resolve-host.php bleuetblanc.ca
 *     db      bleuet-blanc
 *
 *     php tools/resolve-host.php unmapped.example
 *     config  prairiedispatch      (the config fallback answered)
 *
 * The deployment verification loop runs this over every live hostname and
 * requires `db` on each before the config file's tenant arms may retire.
 * Exit codes: 0 = the domains table answered, 2 = the config fallback did.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
$host = strtolower(trim($argv[1] ?? ''));
if ($host === '') {
    fwrite(STDERR, "Usage: php tools/resolve-host.php <hostname>\n");
    exit(64);
}

// Resolution reads $_SERVER['HTTP_HOST'], so stage the hostname exactly as a
// request would carry it — this exercises the real code path, not a copy.
$_SERVER['HTTP_HOST'] = $host;
putenv('PP_SITE');   // the CLI override must not shadow what a request sees

require dirname(__DIR__) . '/app/bootstrap.php';

$fromDb = pp_domain_site_slug();
$slug = current_site()['slug'];
if ($fromDb !== null) {
    printf("db      %s\n", $slug);
    exit(0);
}
printf("config  %-20s (the config fallback answered)\n", $slug);
exit(2);
