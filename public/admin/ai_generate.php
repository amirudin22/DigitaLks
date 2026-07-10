<?php
// AJAX endpoint — dipanggil per batch dari JavaScript
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF check
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['_csrf_token'], $csrfToken)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'CSRF token tidak valid.']);
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$apiKey = $config['groq']['api_key'] ?? '';
if (empty($apiKey)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'GROQ_API_KEY tidak dikonfigurasi di .env']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$batchSize  = (int)($input['batch_size'] ?? 10);
$mapel      = trim($input['mapel'] ?? '');
$kelas      = trim($input['kelas'] ?? '');
$difficulty = trim($input['difficulty'] ?? 'sedang');
$instructions = trim($input['instructions'] ?? '');
$selectedTopics = trim($input['selected_topics'] ?? '');

if (empty($mapel) || empty($kelas) || $batchSize < 1) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Parameter tidak lengkap']);
    exit;
}

$systemPrompt = <<<PROMPT
Anda adalah guru yang membuat soal pilihan ganda untuk siswa.
Output JSON array dengan struktur berikut, tanpa markdown, tanpa teks lain, hanya JSON array:

[
  {
    "pertanyaan": "teks soal",
    "pilihan": ["A. teks pilihan A", "B. teks pilihan B", "C. teks pilihan C", "D. teks pilihan D"],
    "jawaban": "A"
  }
]
PROMPT;

$userPrompt = "Buat {$batchSize} soal {$mapel} untuk tingkat {$kelas}. Kesulitan: {$difficulty}.";
if (!empty($selectedTopics)) {
    $userPrompt .= "\nTopik-topik yang harus dicakup: {$selectedTopics}";
}
if (!empty($instructions)) {
    $userPrompt .= "\nInstruksi tambahan: {$instructions}";
}

$payload = [
    'model'       => $config['groq']['model'] ?? 'mixtral-8x7b-32768',
    'messages'    => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    'temperature' => 0.7,
    'max_tokens'  => 8192,
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'cURL error: ' . $curlErr]);
    exit;
}

$body = json_decode($response, true);

if ($httpCode !== 200 || !isset($body['choices'][0]['message']['content'])) {
    $errMsg = $body['error']['message'] ?? 'Groq API returned HTTP ' . $httpCode;
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $errMsg]);
    exit;
}

$content = trim($body['choices'][0]['message']['content']);

// Bersihkan markdown ```json ... ``` jika ada
$content = preg_replace('/^```(?:json)?\s*/i', '', $content);
$content = preg_replace('/\s*```$/', '', $content);

$questions = json_decode($content, true);
if (!is_array($questions)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Gagal parse JSON dari Groq. Response: ' . substr($content, 0, 500)]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'questions' => $questions]);
