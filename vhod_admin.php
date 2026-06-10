<?php
session_start();
require_once 'functions.php';
if (!empty($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: admin.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $conn = getDatabaseConnection();
        if (!$conn) {
            $error = 'Ошибка подключения к базе данных.';
        } else {
            $stmt = $conn->prepare('SELECT id, login, passwor FROM admin WHERE login = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $login);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res->fetch_assoc();
                $stmt->close();
                if ($user) {
                    $hash = $user['passwor'];
                    if (password_verify($password, $hash)) {
                        session_regenerate_id(true);
                        $_SESSION['admin_logged'] = true;
                        $_SESSION['admin_id'] = (int)$user['id'];
                        $_SESSION['admin_login'] = $user['login'];
                        header('Location: admin.php');
                        exit;
                    } else {
                        $error = 'Неверный логин или пароль.';
                    }
                } else {
                    $error = 'Неверный логин или пароль.';
                }
            } else {
                $error = 'Ошибка запроса к базе данных.';
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход в админ-панель</title>
  <link rel="stylesheet" href="css/stylevhodadmin.css">
</head>
<body>
  <div class="login-container">
    <h1>Вход в панель администратора</h1>
    <form method="post" action="admin.php" autocomplete="off">
      <input type="text" name="login" id="login" placeholder="Логин" value="<?php echo htmlspecialchars($_POST['login'] ?? '', ENT_QUOTES); ?>" required>
      <input type="password" name="password" id="password" placeholder="Пароль" required>
      <button type="submit">Войти</button>
      <button type="button" onclick="window.location.href='index.php'" class="button_index">Вернуться на сайт</button>
    </form>
    <?php if ($error): ?>
      <div id="error" class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
