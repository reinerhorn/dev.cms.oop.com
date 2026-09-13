<?php
$hash = password_hash('TestPasswort123!', PASSWORD_BCRYPT, ['cost' => 12]);

$start = microtime(true);
password_verify('TestPasswort123!', $hash);
$end = microtime(true);

echo 'Apache PHP password_verify: ' . number_format($end - $start, 6) . ' sec';
