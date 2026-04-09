<?php
/**
 * EIA Dashboard - Production Environment Diagnostic Tool
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== EIA Dashboard Environment Diagnostic ===\n\n";

// 1. Check OS
$os = PHP_OS;
echo "1. Operating System: $os\n";

// 2. Check PHP Path
echo "2. Searching for PHP Executable:\n";
if (strtoupper(substr($os, 0, 3)) === 'WIN') {
    $where = shell_exec("where php");
    echo "   Path (Windows): " . ($where ?: "Not found via 'where' command") . "\n";
} else {
    $which = shell_exec("which php");
    echo "   Path (Linux): " . ($which ?: "Not found via 'which' command") . "\n";
}
echo "   Current PHP Binary: " . PHP_BINARY . "\n";

// 3. Check exec() availability
echo "\n3. Checking exec() function:\n";
if (function_exists('exec')) {
    echo "   STATUS: [OK] exec() is enabled.\n";
    $disabled = ini_get('disable_functions');
    if ($disabled) {
        echo "   NOTE: Other disabled functions: $disabled\n";
    }
} else {
    echo "   STATUS: [ERROR] exec() is DISABLED in php.ini.\n";
}

// 4. Test Write Permissions
echo "\n4. Checking Write Permissions:\n";
$test_file = 'test_write_' . time() . '.txt';
$dir = __DIR__;
echo "   Current Directory: $dir\n";

if (is_writable($dir)) {
    echo "   STATUS: [OK] Directory is writable.\n";
    $write_result = @file_put_contents($test_file, "Diagnostic Test");
    if ($write_result !== false) {
        echo "   STATUS: [OK] Successfully created test file.\n";
        @unlink($test_file);
    } else {
        echo "   STATUS: [ERROR] Directory says writable, but file creation failed.\n";
    }
} else {
    echo "   STATUS: [ERROR] Directory is NOT writable. This prevents .lock and .json updates.\n";
}

// 5. Test Background Command Syntax
echo "\n5. Testing Command Syntax:\n";
$php_binary = PHP_BINARY;
$test_worker = $dir . DIRECTORY_SEPARATOR . 'test_worker.php';
file_put_contents($test_worker, "<?php sleep(2); file_put_contents('test_done.txt', 'Done'); ?>");

if (strtoupper(substr($os, 0, 3)) === 'WIN') {
    $cmd = "start /B $php_binary \"$test_worker\" > NUL 2>&1";
    echo "   Proposed Windows Command: $cmd\n";
} else {
    $cmd = "$php_binary \"$test_worker\" > /dev/null 2>&1 &";
    echo "   Proposed Linux Command: $cmd\n";
}

echo "\n============================================\n";
echo "Please check if 'test_done.txt' appears in this folder after 5 seconds.\n";
?>