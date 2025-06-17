<?php
session_start();
require_once '../conect.php';

// Исправлено: добавлена закрывающая скобка для isset
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: auth.php');
    exit;
}

// Обработка операций с администраторами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_admin'])) {
        // Обновление данных администратора
        $new_login = mysqli_real_escape_string($conn, $_POST['new_login']);
        $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);

        $sql = "UPDATE administrators SET login = '$new_login', password = '$new_password' 
                WHERE login = '{$_SESSION['admin_login']}'";
        mysqli_query($conn, $sql);
        $_SESSION['admin_login'] = $new_login;
    } elseif (isset($_POST['add_zone'])) {
        // Добавление новой зоны
        $zone_name = mysqli_real_escape_string($conn, $_POST['zone_name']);
        $sql = "INSERT INTO domain_zones (zone_name) VALUES ('$zone_name')";
        mysqli_query($conn, $sql);
    } elseif (isset($_POST['delete_zone'])) {
        // Удаление зоны
        $zone_id = (int)$_POST['zone_id'];
        $sql = "DELETE FROM domain_zones WHERE zone_id = $zone_id";
        mysqli_query($conn, $sql);
    } elseif (isset($_POST['update_zone'])) {
        // Обновление зоны
        $zone_id = (int)$_POST['zone_id'];
        $new_zone_name = mysqli_real_escape_string($conn, $_POST['new_zone_name']);
        $sql = "UPDATE domain_zones SET zone_name = '$new_zone_name' WHERE zone_id = $zone_id";
        mysqli_query($conn, $sql);
    }
}

// Получаем текущего администратора
$current_admin = mysqli_query($conn, "SELECT * FROM administrators WHERE login = '{$_SESSION['admin_login']}'");
$admin_data = mysqli_fetch_assoc($current_admin);

// Получаем список доменных зон
$zones_result = mysqli_query($conn, "SELECT * FROM domain_zones");
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>

<body>
    <h1>Добро пожаловать, <?= htmlspecialchars($admin_data['login']) ?></h1>
    <a href="auth.php?logout">Выйти</a>

    <!-- Форма изменения данных администратора -->
    <h2>Ваши данные</h2>
    <form method="POST">
        <div>
            <label>Новый логин:</label>
            <input type="text" name="new_login" value="<?= htmlspecialchars($admin_data['login']) ?>" required>
        </div>
        <div>
            <label>Новый пароль:</label>
            <input type="password" name="new_password" value="<?= htmlspecialchars($admin_data['password']) ?>" required>
        </div>
        <button type="submit" name="update_admin">Обновить данные</button>
    </form>

    <!-- Управление доменными зонами -->
    <h2>Управление доменными зонами</h2>

    <!-- Форма добавления новой зоны -->
    <form method="POST">
        <h3>Добавить новую зону</h3>
        <div>
            <label>Имя зоны (например: .com):</label>
            <input type="text" name="zone_name" required>
        </div>
        <button type="submit" name="add_zone">Добавить зону</button>
    </form>

    <!-- Список существующих зон -->
    <h3>Список доменных зон</h3>
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Зона</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($zone = mysqli_fetch_assoc($zones_result)): ?>
                <tr>
                    <td><?= $zone['zone_id'] ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="zone_id" value="<?= $zone['zone_id'] ?>">
                            <input type="text" name="new_zone_name" value="<?= htmlspecialchars($zone['zone_name']) ?>">
                            <button type="submit" name="update_zone">Обновить</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Удалить эту зону?');">
                            <input type="hidden" name="zone_id" value="<?= $zone['zone_id'] ?>">
                            <button type="submit" name="delete_zone">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>

</html>