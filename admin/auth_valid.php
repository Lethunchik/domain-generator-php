<?php
session_start();
require_once '../conect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = mysqli_real_escape_string($conn, $_POST['login']);
  $password = mysqli_real_escape_string($conn, $_POST['password']);

  $sql = "SELECT * FROM administrators WHERE login = '$login' AND password = '$password'";
  $result = mysqli_query($conn, $sql);

  if (mysqli_num_rows($result) === 1) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login'] = $login;
    header('Location: admin.php');
    exit;
  } else {
    $_SESSION['login_error'] = "Неверный логин или пароль";
    header('Location: auth.php');
    exit;
  }
} else {
  header('Location: auth.php');
  exit;
}
