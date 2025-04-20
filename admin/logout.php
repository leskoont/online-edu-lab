<?php
session_start();

// Удаление всех переменных сессии
$_SESSION = array();

// Если используются куки сессии, их тоже нужно уничтожить
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Уничтожение сессии
session_destroy();

// Перенаправление на страницу входа
header('Location: login.php');
exit;
?> 