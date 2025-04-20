<?php
session_start();

// Очистка всех переменных сессии
$_SESSION = array();

// Если используются сессионные cookie, отправляем клиенту cookie с истекшим сроком действия
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Уничтожение сессии
session_destroy();

// Перенаправление на главную страницу
header("Location: ../index.php");
exit;
?> 