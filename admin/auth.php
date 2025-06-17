<?php
session_start();
// Обработка выхода
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: auth.php');
  exit;
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
  header('Location: admin.php');
  exit;
}


if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
  header('Location: admin.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Авторизация администратора</title>
  <link rel="stylesheet" href="../css/auth.css">
</head>

<body>
  <h1>Вход в панель администратора</h1>
  <?php if (isset($_SESSION['login_error'])): ?>
    <p style="color:red;"><?= $_SESSION['login_error'] ?></p>
    <?php unset($_SESSION['login_error']); ?>
  <?php endif; ?>
  <form action="auth_valid.php" method="POST">
    <div>
      <label for="login">Логин:</label>
      <input type="text" id="login" name="login" required>
    </div>
    <div>
      <label for="password">Пароль:</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit">Войти</button>
  </form>
</body>

</html>