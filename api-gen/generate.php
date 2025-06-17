<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/generate_errors.log');

// Очистка буфера
while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');

function return_error($message, $code = 500)
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function log_debug($message)
{
    file_put_contents(__DIR__ . '/debug.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

log_debug('===== NEW REQUEST STARTED =====');

try {
    // Проверка метода
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return_error('Only POST method allowed', 405);
    }

    // Проверка заголовка
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'application/json') === false) {
        return_error('Invalid Content-Type. Expected application/json', 400);
    }

    // Получение данных
    $json_input = file_get_contents('php://input');
    log_debug("Raw input: " . $json_input);

    $data = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return_error('Invalid JSON: ' . json_last_error_msg(), 400);
    }

    if (empty($data['text'])) {
        return_error('Text is required', 400);
    }

    if (empty($data['zone_id']) || !is_numeric($data['zone_id'])) {
        return_error('Invalid zone ID', 400);
    }

    $text = trim($data['text']);
    $zone_id = (int)$data['zone_id'];

    log_debug("Received request - Text: '$text', Zone ID: $zone_id");

    // Подключение к БД
    require_once __DIR__ . '/../conect.php';

    if (!$conn) {
        return_error('Database connection failed', 500);
    }

    // Получение доменной зоны
    $zone_query = mysqli_prepare($conn, "SELECT zone_name FROM domain_zones WHERE zone_id = ?");
    if (!$zone_query) {
        return_error('DB prepare failed: ' . mysqli_error($conn), 500);
    }

    mysqli_stmt_bind_param($zone_query, 'i', $zone_id);
    mysqli_stmt_execute($zone_query);
    $zone_result = mysqli_stmt_get_result($zone_query);

    if (!$zone_result || mysqli_num_rows($zone_result) === 0) {
        return_error('Domain zone not found', 404);
    }

    $zone_row = mysqli_fetch_assoc($zone_result);
    $zone = $zone_row['zone_name'];
    log_debug("Found zone: $zone");

    // Формирование промпта
    $prompt = "Придумай 40 креативных доменных имен в зоне $zone для сайта $text. Формат твоего ответа: каждый домен с новой строки, в формате example$zone и больше ничего";
    log_debug("Prompt: $prompt");

    // Отправка задачи в Gen-API
    $api_key = 'sk-fdtqzJBmpZq45ZJ38EBsWVlWDC4iIzz1ZBkOw6kARFr44Oy7pNdTN50DFYPe';
    $api_url = 'https://api.gen-api.ru/api/v1/networks/deepseek-v3';

    $post_data = [
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ];

    log_debug("Sending request to Gen-API: " . json_encode($post_data));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = 'cURL error: ' . curl_error($ch);
        log_debug($error_msg);
        return_error($error_msg, 502);
    }

    curl_close($ch);

    log_debug("Gen-API response code: $http_code");
    log_debug("Gen-API response: " . $api_response);

    if ($http_code !== 200) {
        return_error("Gen-API returned $http_code status", 502);
    }

    $api_data = json_decode($api_response, true);
    if (!$api_data || json_last_error() !== JSON_ERROR_NONE) {
        return_error('Invalid response from Gen-API', 502);
    }

    // Проверяем наличие request_id
    if (empty($api_data['request_id'])) {
        return_error('Missing request_id in API response', 502);
    }

    $request_id = $api_data['request_id'];
    log_debug("Received request_id: $request_id");

    // Возвращаем request_id клиенту
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'request_id' => $request_id,
        'input' => [
            'text' => $text,
            'zone' => $zone
        ]
    ]);
    log_debug('Request ID sent to client');
} catch (Exception $e) {
    log_debug('Exception: ' . $e->getMessage());
    return_error('Internal server error', 500);
}
