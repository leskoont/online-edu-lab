<?php
session_start();
require_once '../config.php';

// Если уже авторизован, перенаправляем на панель управления
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header('Location: student_dashboard.php');
    exit;
}

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        // Поиск пользователя в базе
        $stmt = $pdo->prepare("
            SELECT account_id, username, password, role
            FROM accounts
            WHERE username = :username AND role = 'student'
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        // Проверка пароля (в реальном проекте нужно использовать хеширование)
        if ($user && $password === $user['password']) { // В реальном проекте: password_verify($password, $user['password'])
            // Обновляем время последнего входа
            $updateStmt = $pdo->prepare("UPDATE accounts SET last_login = NOW() WHERE account_id = :id");
            $updateStmt->execute([':id' => $user['account_id']]);
            
            // Создаем сессию
            $_SESSION['user_id'] = $user['account_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Перенаправляем на панель управления
            header('Location: student_dashboard.php');
            exit;
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход для студентов | Платформа онлайн образования</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 400px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #34495e;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #2c3e50;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Вход для студентов</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Имя пользователя:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Войти</button>
        </form>
        
        <div class="links">
            <a href="../index.php">На главную</a> | 
            <a href="teacher_login.php">Вход для преподавателей</a>
        </div>
    </div>
</body>
</html> 