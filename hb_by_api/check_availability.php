<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/hb_api_errors.log');

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

log_debug('===== HB.by DOMAIN CHECK STARTED =====');

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

    if (empty($data['domains']) || !is_array($data['domains'])) {
        return_error('Domains list is required and must be an array', 400);
    }

    $domains = $data['domains'];
    $api_token = 'C583FD55-159B-4C7E-8E11-A52C6ACB6E8C'; // Замените на реальный ключ

    // Ограничиваем количество доменов (максимум 50)
    if (count($domains) > 50) {
        $domains = array_slice($domains, 0, 50);
    }

    log_debug("Checking availability for domains: " . implode(', ', $domains));

    // Отправка запроса к API hb.by
    $api_url = 'https://api.hb.by/v1/check-domains-availability';

    $post_data = [
        'token' => $api_token,
        'domains' => $domains
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
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

    log_debug("HB.by API response code: $http_code");
    log_debug("HB.by API response: " . $api_response);

    if ($http_code !== 200) {
        return_error("HB.by API returned $http_code status", 502);
    }

    $api_data = json_decode($api_response, true);
    if (!$api_data || json_last_error() !== JSON_ERROR_NONE) {
        return_error('Invalid response from HB.by API', 502);
    }

    // Фильтруем только свободные домены и берем первые 10
    $available_domains = [];
    foreach ($api_data['domains'] as $domain_info) {
        if (!$domain_info['is_registered']) {
            $available_domains[] = $domain_info['domain'];
            if (count($available_domains) >= 10) {
                break; // Ограничиваем 10 доменами
            }
        }
    }

    // Возвращаем только свободные домены
    $result = [
        'success' => true,
        'available_domains' => $available_domains
    ];

    // Возвращаем результат
    ob_end_clean();
    echo json_encode($result);
    log_debug('Domain availability check completed. Found: ' . count($available_domains));
} catch (Exception $e) {
    log_debug('Exception: ' . $e->getMessage());
    return_error('Internal server error', 500);
}
