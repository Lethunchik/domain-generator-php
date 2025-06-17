<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/status_errors.log');

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

log_debug('===== STATUS CHECK STARTED =====');

try {
    // Проверка параметров
    if (empty($_GET['request_id'])) {
        return_error('Missing request_id parameter', 400);
    }

    $request_id = $_GET['request_id'];
    log_debug("Checking status for request_id: $request_id");

    $api_key = 'sk-fdtqzJBmpZq45ZJ38EBsWVlWDC4iIzz1ZBkOw6kARFr44Oy7pNdTN50DFYPe';
    $api_url = "https://api.gen-api.ru/api/v1/request/get/$request_id";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = 'cURL error: ' . curl_error($ch);
        log_debug($error_msg);
        return_error($error_msg, 502);
    }

    curl_close($ch);

    log_debug("Gen-API status response code: $http_code");
    log_debug("Gen-API status response: " . $api_response);

    if ($http_code !== 200) {
        return_error("Gen-API returned $http_code status", 502);
    }

    $status_data = json_decode($api_response, true);
    if (!$status_data || json_last_error() !== JSON_ERROR_NONE) {
        return_error('Invalid status response from Gen-API', 502);
    }

    // Проверяем статус задачи
    $status = $status_data['status'] ?? 'unknown';
    $result = [
        'request_id' => $request_id,
        'status' => $status,
        'response' => $status_data
    ];

    // Если задача завершена, извлекаем домены
    if (($status === 'completed' || $status === 'success') && isset($status_data['result'])) {
        // Получаем результат как строку с доменами
        $content = is_array($status_data['result']) ? $status_data['result'][0] : $status_data['result'];

        // Разбиваем на отдельные домены
        $domains = explode("\n", $content);
        $processed_domains = [];

        foreach ($domains as $domain) {
            $domain = trim($domain);

            // Убираем нумерацию (1., 2. и т.д.)
            if (preg_match('/^\d+\.\s*(.+)/', $domain, $matches)) {
                $domain = $matches[1];
            }

            // Убираем лишние символы и двойные пробелы
            $domain = trim($domain, "- \t\n\r\0\x0B");
            $domain = preg_replace('/\s{2,}/', ' ', $domain);

            // Проверяем, что это похоже на домен
            if (!empty($domain) && strpos($domain, '.') !== false) {
                $processed_domains[] = $domain;
            }
        }

        $result['domains'] = $processed_domains;
        log_debug("Processed " . count($processed_domains) . " domains from result field");
    }
    // Альтернативный вариант, если данные в другом формате
    else if (($status === 'completed' || $status === 'success') && isset($status_data['full_response'][0]['choices'][0]['message']['content'])) {
        $content = $status_data['full_response'][0]['choices'][0]['message']['content'];
        $domains = explode("\n", $content);
        $processed_domains = [];

        foreach ($domains as $domain) {
            $domain = trim($domain);

            if (preg_match('/^\d+\.\s*(.+)/', $domain, $matches)) {
                $domain = $matches[1];
            }

            $domain = trim($domain, "- \t\n\r\0\x0B");
            $domain = preg_replace('/\s{2,}/', ' ', $domain);

            if (!empty($domain) && strpos($domain, '.') !== false) {
                $processed_domains[] = $domain;
            }
        }

        $result['domains'] = $processed_domains;
        log_debug("Processed " . count($processed_domains) . " domains from full_response");
    }

    // Возвращаем статус
    ob_end_clean();
    echo json_encode($result);
    log_debug("Status check completed: $status");
} catch (Exception $e) {
    log_debug('Exception: ' . $e->getMessage());
    return_error('Internal server error', 500);
}
