#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/EmailQueue.php';

$processed = EmailQueue::process(20);

if ($processed > 0) {
    echo date('Y-m-d H:i:s') . " - Processed {$processed} email(s)\n";
}
