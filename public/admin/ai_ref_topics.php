<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../../src/Core/PromptGenerator.php';

$ref = @\Core\PromptGenerator::MATERI_REFERENCE;
if (!is_array($ref)) {
    echo '{}';
    exit;
}

echo json_encode($ref, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
