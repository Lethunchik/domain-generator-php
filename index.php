<?php
require_once 'conect.php';

$zones = [];
$result = mysqli_query($conn, "SELECT zone_id, zone_name FROM domain_zones");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $zones[$row['zone_id']] = $row['zone_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Генератор доменных имен</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <h1>Генератор доменных имен</h1>

    <form id="domainForm">
        <div>
            <label for="domain_text">Тема сайта:</label>
            <input type="text" id="domain_text" name="text" required placeholder="Введите тему сайта">
        </div>

        <div>
            <label for="domain_zone">Доменная зона:</label>
            <select id="domain_zone" name="zone_id" required>
                <option value="">-- Выберите зону --</option>
                <?php foreach ($zones as $id => $zone): ?>
                    <option value="<?= $id ?>"><?= htmlspecialchars($zone) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit">Сгенерировать домены</button>
    </form>

    <div id="loading">Идет генерация и проверка доменов, пожалуйста подождите...</div>

    <div id="resultContainer" class="result"></div>

    <script>
        document.getElementById('domainForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = e.target;
            const text = form.text.value.trim();
            const zone_id = form.zone_id.value;

            if (!text || !zone_id) {
                showError('Заполните все поля');
                return;
            }

            // Сброс предыдущих результатов
            resetUI();

            // Показываем индикатор загрузки
            document.getElementById('loading').style.display = 'block';

            try {
                // Отправляем запрос на создание задачи
                const createResponse = await fetch('api-gen/generate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        text,
                        zone_id
                    })
                });

                const createData = await createResponse.json();

                if (createData.error) {
                    showError(createData.error);
                    return;
                }

                if (!createData.request_id) {
                    showError('Не удалось получить ID задачи');
                    return;
                }

                const requestId = createData.request_id;

                // Запускаем проверку статуса
                checkStatus(requestId);

            } catch (error) {
                showError('Сетевая ошибка: ' + error.message);
                document.getElementById('loading').style.display = 'none';
            }
        });

        async function checkStatus(requestId) {
            try {
                // Отправляем запрос на проверку статуса
                const statusResponse = await fetch(`api-gen/check_status.php?request_id=${requestId}`);
                const statusData = await statusResponse.json();

                if (statusData.error) {
                    showError(statusData.error);
                    document.getElementById('loading').style.display = 'none';
                    return;
                }

                // Обработка разных статусов
                const status = statusData.status || 'unknown';

                if (status === 'completed' || status === 'success') {
                    // Если генерация завершена, проверяем доступность доменов
                    checkDomainsAvailability(statusData.domains);
                } else if (status === 'processing') {
                    // Повторная проверка через 3 секунды
                    setTimeout(() => checkStatus(requestId), 3000);
                } else if (status === 'pending') {
                    // Повторная проверка через 2 секунды
                    setTimeout(() => checkStatus(requestId), 2000);
                } else if (status === 'failed') {
                    showError('Задача завершилась с ошибкой');
                    document.getElementById('loading').style.display = 'none';
                } else {
                    // Неизвестный статус - повторяем через 5 секунд
                    setTimeout(() => checkStatus(requestId), 5000);
                }

            } catch (error) {
                showError('Ошибка проверки статуса: ' + error.message);
                document.getElementById('loading').style.display = 'none';
            }
        }

        async function checkDomainsAvailability(domains) {
            try {
                if (!domains || domains.length === 0) {
                    showError('Нет доменов для проверки');
                    return;
                }

                // Отправляем запрос на проверку доступности
                const response = await fetch('hb_by_api/check_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        domains
                    })
                });

                const data = await response.json();

                if (data.error) {
                    showError(data.error);
                    return;
                }

                // Отображаем результаты в таблице
                renderResults(data.available_domains);

            } catch (error) {
                showError('Ошибка при проверке доступности: ' + error.message);
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }

        function renderResults(domains) {
            let resultHtml = '<h2>Свободные домены</h2>';

            if (domains && domains.length > 0) {
                resultHtml += `
                <table class="domains-table">
                    <thead>
                        <tr>
                            <th>Домен</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>`;

                domains.forEach(domain => {
                    resultHtml += `
                    <tr>
                        <td>${escapeHtml(domain)}</td>
                        <td><button class="action-btn" onclick="registerDomain('${escapeHtml(domain)}')">Зарегистрировать</button></td>
                    </tr>`;
                });

                resultHtml += `</tbody></table>`;
            } else {
                resultHtml += '<p class="error">Нет свободных доменов</p>';
            }

            document.getElementById('resultContainer').innerHTML = resultHtml;
            document.getElementById('resultContainer').style.display = 'block';
        }

        // function registerDomain(domain) {
        //     // Открываем страницу регистрации в новом окне
        //     const registerWindow = window.open(`domain_buy/register.php?domain=${encodeURIComponent(domain)}`, '_blank', 'width=500,height=300');

        //     // Закрываем вспомогательное окно через 5 секунд
        //     setTimeout(() => {
        //         if (registerWindow && !registerWindow.closed) {
        //             registerWindow.close();
        //         }
        //     }, 50000000000000000000000000000000000000000000);
        // }

        // function registerDomain(domain) {
        //     // Открываем сразу страницу hb.by с параметром домена
        //     window.open(`domain_buy/register.php?domain=${encodeURIComponent(domain)}`, '_blank');
        // }

        function registerDomain(domain) {
            // Прямое перенаправление на hb.by с параметром
            window.location.href = `https://hb.by/domains?domain=${encodeURIComponent(domain)}`;
        }

        function showError(message) {
            document.getElementById('resultContainer').innerHTML =
                `<div class="error">${escapeHtml(message)}</div>`;
            document.getElementById('resultContainer').style.display = 'block';
            document.getElementById('loading').style.display = 'none';
        }

        function resetUI() {
            document.getElementById('resultContainer').innerHTML = '';
            document.getElementById('resultContainer').style.display = 'none';
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>