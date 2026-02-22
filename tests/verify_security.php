<?php
require __DIR__ . '/../admin.php';

function test_safe_url() {
    $tests = [
        ['url' => 'http://google.com', 'expected' => true],
        ['url' => 'https://google.com', 'expected' => true],
        ['url' => 'mailto:test@example.com', 'expected' => true],
        ['url' => 'tel:+123456789', 'expected' => true],
        ['url' => 'javascript:alert(1)', 'expected' => false],
        ['url' => 'JAVASCRIPT:alert(1)', 'expected' => false],
        ['url' => 'data:text/html,<script>alert(1)</script>', 'expected' => false],
        ['url' => 'www.google.com', 'expected' => true],
        ['url' => '/path/to/icon.png', 'expected' => true],
        ['url' => '', 'expected' => true],
    ];

    foreach ($tests as $t) {
        $result = is_safe_url($t['url']);
        echo ($result === $t['expected'] ? "PASS" : "FAIL") . ": '{$t['url']}' (expected " . ($t['expected'] ? "true" : "false") . ")\n";
    }
}

test_safe_url();
