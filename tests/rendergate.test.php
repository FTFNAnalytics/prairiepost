<?php
/**
 * The CI render gate (F11): [render] in a PR title may excuse ONLY a
 * classified rendered-output difference (baseline exit 10) — never a seed
 * failure, a server error, or broken comparison infrastructure — and a
 * hostile title is inert data, not shell source.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$gate = $root . '/tools/render-gate.sh';

$fails = 0;
function ok(bool $cond, string $label): void
{
    global $fails;
    if (!$cond) {
        echo "FAIL $label\n";
        $fails++;
    }
}

/** Run the gate with a given baseline exit code and PR title. */
function gate(int $rc, string $title): array
{
    global $gate;
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p = proc_open(['bash', $gate, (string) $rc], $spec, $pipes, null, ['PR_TITLE' => $title, 'PATH' => getenv('PATH')]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $exit = proc_close($p);
    return [$exit, $out];
}

$marker = sys_get_temp_dir() . '/pp-gate-pwned-' . getmypid();
@unlink($marker);
$hostile = '"; touch ' . $marker . '; $(touch ' . $marker . ') `touch ' . $marker . '` \'; echo pwned # [render]';
$hostileNoRender = '$(touch ' . $marker . ') && `touch ' . $marker . '`; "broken \' title';

// Success passes with or without a declaration.
[$exit] = gate(0, '');
ok($exit === 0, 'clean baseline passes');
[$exit] = gate(0, 'Fix things [render]');
ok($exit === 0, 'clean baseline passes with a stray declaration');

// A render difference needs the declaration…
[$exit, $out] = gate(10, 'A quiet refactor');
ok($exit === 1 && str_contains($out, '[render]'), 'undeclared render change fails with guidance');
[$exit] = gate(10, 'New masthead [render]');
ok($exit === 0, 'declared render change passes');

// …and the declaration excuses NOTHING else.
foreach ([2 => 'seed failure', 3 => 'smoke failure', 4 => 'comparison infrastructure', 5 => 'no pages',
          6 => 'head fixture migration failure', 7 => 'fixture divergence', 42 => 'unclassified'] as $rc => $label) {
    [$exit] = gate($rc, 'Break everything [render]');
    ok($exit === 1, "[render] cannot excuse: $label (rc=$rc)");
}

// A hostile title is data. It flows through as a case-match only: nothing
// in it executes, whatever shell metacharacters it carries.
[$exit] = gate(10, $hostile);
ok($exit === 0, 'hostile title containing [render] still gates correctly');
ok(!file_exists($marker), 'hostile title with [render] executed nothing');
[$exit] = gate(10, $hostileNoRender);
ok($exit === 1, 'hostile title without [render] fails the gate');
ok(!file_exists($marker), 'hostile title without [render] executed nothing');
[$exit] = gate(3, $hostile);
ok($exit === 1 && !file_exists($marker), 'hostile title against a smoke failure: fails, executes nothing');

@unlink($marker);
exit($fails ? 1 : 0);
