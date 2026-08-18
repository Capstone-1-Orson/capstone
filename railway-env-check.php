<?php
// TEMPORARY DEBUG FILE — DELETE AFTER USE
header('Content-Type: text/plain');

echo "MYSQLHOST: "     . var_export(getenv('MYSQLHOST'), true)     . "\n";
echo "MYSQLUSER: "     . var_export(getenv('MYSQLUSER'), true)     . "\n";
echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? '[set, hidden]' : var_export(getenv('MYSQLPASSWORD'), true)) . "\n";
echo "MYSQLDATABASE: " . var_export(getenv('MYSQLDATABASE'), true) . "\n";
echo "MYSQLPORT: "     . var_export(getenv('MYSQLPORT'), true)     . "\n";

echo "\n--- \$_ENV check ---\n";
echo "MYSQLHOST via \$_ENV: " . var_export($_ENV['MYSQLHOST'] ?? 'NOT SET', true) . "\n";

echo "\n--- \$_SERVER check ---\n";
echo "MYSQLHOST via \$_SERVER: " . var_export($_SERVER['MYSQLHOST'] ?? 'NOT SET', true) . "\n";