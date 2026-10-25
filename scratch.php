<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Scratch file to test code
