<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Получение статистики из базы данных
$stats = [];

// Количество курсов
$stmt = $pdo->query("SELECT COUNT(*) FROM courses");
$stats['courses'] = $stmt->fetchColumn();

// Количество преподавателей
$stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
$stats['teachers'] = $stmt->fetchColumn();

// Количество уроков
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons");
$stats['lessons'] = $stmt->fetchColumn();

// Количество аккаунтов
$stmt = $pdo->query("SELECT COUNT(*) FROM accounts");
$stats['accounts'] = $stmt->fetchColumn();

// Количество студентов
$stmt = $pdo->query("SELECT COUNT(*) FROM accounts WHERE role = 'student'");
$stats['students'] = $stmt->fetchColumn();

// Последние 5 зарегистрированных аккаунтов
$stmt = $pdo->query("SELECT account_id, username, email, role, created_at FROM accounts ORDER BY created_at DESC LIMIT 5");
$recentAccounts = $stmt->fetchAll();

// Последние 5 добавленных курсов
$stmt = $pdo->query("
    SELECT c.course_id, c.title, c.status, c.created_at, CONCAT(t.first_name, ' ', t.last_name) as teacher_name
    FROM courses c
    JOIN teachers t ON c.teacher_id = t.teacher_id
    ORDER BY c.created_at DESC
    LIMIT 5
");
$recentCourses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора | Платформа онлайн образования</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 90%;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 20px 0;
            text-align: center;
        }
        .header-content {
            width: 90%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-content h1 {
            margin: 0;
        }
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 3px;
            transition: background-color 0.3s;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        nav {
            background-color: #34495e;
            padding: 10px 0;
        }
        nav ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        nav ul li {
            display: inline-block;
            margin: 0 10px;
        }
        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        nav ul li a:hover {
            background-color: #2c3e50;
        }
        .section {
            margin: 20px 0;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .stats {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .stat-box {
            background-color: #ecf0f1;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            width: 18%;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-box h3 {
            margin: 0;
            font-size: 14px;
            color: #7f8c8d;
        }
        .stat-box p {
            margin: 10px 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 3px;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-action {
            padding: 5px 10px;
            font-size: 12px;
        }
        .welcome {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #ecf0f1;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-to-site {
            background-color: #3498db;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Панель администратора</h1>
            <div>
                <a href="../index.php" class="logout-btn back-to-site">На сайт</a>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </div>
    </header>
    <nav>
        <ul>
            <li><a href="index.php">Главная</a></li>
            <li><a href="accounts.php">Пользователи</a></li>
            <li><a href="teachers.php">Преподаватели</a></li>
            <li><a href="courses.php">Курсы</a></li>
            <li><a href="lessons.php">Уроки</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="welcome">
            <h2>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        </div>
        
        <div class="section">
            <h2>Статистика платформы</h2>
            <div class="stats">
                <div class="stat-box">
                    <h3>Пользователи</h3>
                    <p><?php echo $stats['accounts']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Студенты</h3>
                    <p><?php echo $stats['students']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Преподаватели</h3>
                    <p><?php echo $stats['teachers']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Курсы</h3>
                    <p><?php echo $stats['courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Уроки</h3>
                    <p><?php echo $stats['lessons']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="grid">
            <div class="section">
                <h2>Последние добавленные пользователи</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Имя пользователя</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Дата регистрации</th>
                    </tr>
                    <?php foreach ($recentAccounts as $account): ?>
                    <tr>
                        <td><?php echo $account['account_id']; ?></td>
                        <td><?php echo htmlspecialchars($account['username']); ?></td>
                        <td><?php echo htmlspecialchars($account['email']); ?></td>
                        <td>
                            <?php
                            switch ($account['role']) {
                                case 'admin': echo 'Администратор'; break;
                                case 'teacher': echo 'Преподаватель'; break;
                                case 'student': echo 'Студент'; break;
                                default: echo $account['role'];
                            }
                            ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($account['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <a href="accounts.php" class="btn">Все пользователи</a>
            </div>
            
            <div class="section">
                <h2>Последние добавленные курсы</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Преподаватель</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                    </tr>
                    <?php foreach ($recentCourses as $course): ?>
                    <tr>
                        <td><?php echo $course['course_id']; ?></td>
                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                        <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                        <td>
                            <?php
                            switch ($course['status']) {
                                case 'published': echo 'Опубликован'; break;
                                case 'draft': echo 'Черновик'; break;
                                default: echo $course['status'];
                            }
                            ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($course['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <a href="courses.php" class="btn">Все курсы</a>
            </div>
        </div>
        
        <div class="section">
            <h2>Быстрые действия</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="accounts.php" class="btn">Управление пользователями</a>
                <a href="teachers.php" class="btn">Управление преподавателями</a>
                <a href="courses.php" class="btn">Управление курсами</a>
                <a href="lessons.php" class="btn">Управление уроками</a>
            </div>
        </div>
    </div>
</body>
</html> 