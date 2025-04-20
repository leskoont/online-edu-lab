<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: teacher_login.php');
    exit;
}

// Получение информации о преподавателе
$teacherId = $_SESSION['teacher_id'];
$stmt = $pdo->prepare("
    SELECT t.*, a.email, a.username
    FROM teachers t
    JOIN accounts a ON t.account_id = a.account_id
    WHERE t.teacher_id = :teacher_id
");
$stmt->execute([':teacher_id' => $teacherId]);
$teacher = $stmt->fetch();

// Статистика по курсам преподавателя
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_courses,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_courses,
        (SELECT COUNT(*) FROM lessons l JOIN courses c ON l.course_id = c.course_id WHERE c.teacher_id = :teacher_id) as total_lessons,
        (SELECT COUNT(*) FROM enrollments e JOIN courses c ON e.course_id = c.course_id WHERE c.teacher_id = :teacher_id) as total_enrollments
    FROM 
        courses
    WHERE 
        teacher_id = :teacher_id
");
$stmt->execute([':teacher_id' => $teacherId]);
$stats = $stmt->fetch();

// Последние 5 зарегистрированных студентов на курсы преподавателя
$stmt = $pdo->prepare("
    SELECT 
        a.username, a.email, c.title as course_title, e.enrolled_at
    FROM 
        enrollments e
    JOIN 
        accounts a ON e.account_id = a.account_id
    JOIN 
        courses c ON e.course_id = c.course_id
    WHERE 
        c.teacher_id = :teacher_id
    ORDER BY 
        e.enrolled_at DESC
    LIMIT 5
");
$stmt->execute([':teacher_id' => $teacherId]);
$recentEnrollments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель преподавателя | Платформа онлайн образования</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 20px 0;
            text-align: center;
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
        h1, h2 {
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
        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logout {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .logout:hover {
            background-color: #c0392b;
        }
        .btn {
            display: inline-block;
            background-color: #2c3e50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 3px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #34495e;
        }
    </style>
</head>
<body>
    <header>
        <h1>Панель преподавателя</h1>
    </header>
    <nav>
        <ul>
            <li><a href="teacher_dashboard.php">Панель</a></li>
            <li><a href="teacher_courses.php">Мои курсы</a></li>
            <li><a href="../index.php">На главную</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="section welcome">
            <h2>Добро пожаловать, <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>!</h2>
            <a href="logout.php" class="logout">Выйти</a>
        </div>
        
        <div class="section">
            <h2>Статистика</h2>
            <div class="stats">
                <div class="stat-box">
                    <h3>Всего курсов</h3>
                    <p><?php echo $stats['total_courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Опубликовано</h3>
                    <p><?php echo $stats['published_courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Черновики</h3>
                    <p><?php echo $stats['draft_courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Всего уроков</h3>
                    <p><?php echo $stats['total_lessons']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Студентов</h3>
                    <p><?php echo $stats['total_enrollments']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Мои курсы</h2>
            <a href="teacher_courses.php" class="btn">Управление курсами</a>
            <p>На этой странице вы можете создавать новые курсы и редактировать существующие.</p>
        </div>
        
        <div class="section">
            <h2>Последние регистрации на курсы</h2>
            <?php if (count($recentEnrollments) > 0): ?>
                <table>
                    <tr>
                        <th>Студент</th>
                        <th>Email</th>
                        <th>Курс</th>
                        <th>Дата регистрации</th>
                    </tr>
                    <?php foreach ($recentEnrollments as $enrollment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($enrollment['username']); ?></td>
                        <td><?php echo htmlspecialchars($enrollment['email']); ?></td>
                        <td><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($enrollment['enrolled_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Пока нет регистраций на ваши курсы.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 