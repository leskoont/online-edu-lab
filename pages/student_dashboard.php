<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Получение информации о студенте
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE account_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch();

// Статистика по курсам, на которые записан студент
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
        SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_courses,
        AVG(e.progress) as avg_progress
    FROM 
        enrollments e 
    WHERE 
        e.account_id = :user_id
");
$stmt->execute([':user_id' => $userId]);
$stats = $stmt->fetch();

// Курсы студента
$stmt = $pdo->prepare("
    SELECT 
        c.course_id,
        c.title,
        c.category,
        c.level,
        e.progress,
        e.status,
        e.enrolled_at,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) as total_lessons,
        (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.lesson_id 
         WHERE l.course_id = c.course_id AND lp.account_id = :user_id AND lp.status = 'completed') as completed_lessons
    FROM 
        enrollments e
    JOIN 
        courses c ON e.course_id = c.course_id
    JOIN 
        teachers t ON c.teacher_id = t.teacher_id
    WHERE 
        e.account_id = :user_id
    ORDER BY 
        e.enrolled_at DESC
");
$stmt->execute([':user_id' => $userId]);
$courses = $stmt->fetchAll();

// Последние пройденные уроки
$stmt = $pdo->prepare("
    SELECT 
        l.title as lesson_title,
        c.title as course_title,
        c.course_id,
        l.lesson_id,
        lp.completed_at,
        lp.score
    FROM 
        lesson_progress lp
    JOIN 
        lessons l ON lp.lesson_id = l.lesson_id
    JOIN 
        courses c ON l.course_id = c.course_id
    WHERE 
        lp.account_id = :user_id AND lp.status = 'completed'
    ORDER BY 
        lp.completed_at DESC
    LIMIT 5
");
$stmt->execute([':user_id' => $userId]);
$recentLessons = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет | Платформа онлайн образования</title>
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
            width: 22%;
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
            margin-right: 5px;
        }
        .btn:hover {
            background-color: #34495e;
        }
        .progress-container {
            width: 100%;
            background-color: #f1f1f1;
            border-radius: 3px;
            height: 20px;
            margin-top: 5px;
        }
        .progress-bar {
            height: 20px;
            border-radius: 3px;
            background-color: #2ecc71;
            text-align: center;
            color: white;
            line-height: 20px;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 10px;
            color: white;
        }
        .badge-active {
            background-color: #2ecc71;
        }
        .badge-completed {
            background-color: #3498db;
        }
        .badge-dropped {
            background-color: #95a5a6;
        }
    </style>
</head>
<body>
    <header>
        <h1>Личный кабинет студента</h1>
    </header>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Главная</a></li>
            <li><a href="student_courses.php">Мои курсы</a></li>
            <li><a href="../courses.php">Каталог курсов</a></li>
            <li><a href="../index.php">На главную</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="section welcome">
            <h2>Добро пожаловать, <?php echo htmlspecialchars($user['username']); ?>!</h2>
            <a href="logout.php" class="logout">Выйти</a>
        </div>
        
        <div class="section">
            <h2>Статистика обучения</h2>
            <div class="stats">
                <div class="stat-box">
                    <h3>Всего курсов</h3>
                    <p><?php echo $stats['total_courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Активных курсов</h3>
                    <p><?php echo $stats['active_courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Завершенных курсов</h3>
                    <p><?php echo $stats['completed_courses']; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Средний прогресс</h3>
                    <p><?php echo round($stats['avg_progress']) . '%'; ?></p>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Мои курсы</h2>
            <a href="student_courses.php" class="btn">Все мои курсы</a>
            
            <?php if (count($courses) > 0): ?>
                <table>
                    <tr>
                        <th>Название курса</th>
                        <th>Преподаватель</th>
                        <th>Прогресс</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                    <?php foreach (array_slice($courses, 0, 3) as $course): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                        <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                        <td>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $course['progress']; ?>%">
                                    <?php echo round($course['progress']); ?>%
                                </div>
                            </div>
                            <small>
                                <?php echo $course['completed_lessons']; ?> из <?php echo $course['total_lessons']; ?> уроков пройдено
                            </small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $course['status']; ?>">
                                <?php 
                                    switch ($course['status']) {
                                        case 'active': echo 'Активный'; break;
                                        case 'completed': echo 'Завершен'; break;
                                        case 'dropped': echo 'Отменен'; break;
                                        default: echo $course['status'];
                                    }
                                ?>
                            </span>
                        </td>
                        <td>
                            <a href="course_view.php?id=<?php echo $course['course_id']; ?>" class="btn">Перейти к курсу</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Вы еще не записаны ни на один курс. <a href="../courses.php">Посмотрите наш каталог курсов</a>.</p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>Последние пройденные уроки</h2>
            
            <?php if (count($recentLessons) > 0): ?>
                <table>
                    <tr>
                        <th>Урок</th>
                        <th>Курс</th>
                        <th>Дата завершения</th>
                        <th>Оценка</th>
                        <th>Действия</th>
                    </tr>
                    <?php foreach ($recentLessons as $lesson): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lesson['lesson_title']); ?></td>
                        <td><?php echo htmlspecialchars($lesson['course_title']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($lesson['completed_at'])); ?></td>
                        <td>
                            <?php 
                                if ($lesson['score'] !== null) {
                                    echo $lesson['score'] . '/100';
                                } else {
                                    echo 'Не оценивается';
                                }
                            ?>
                        </td>
                        <td>
                            <a href="lesson_view.php?id=<?php echo $lesson['lesson_id']; ?>" class="btn">Повторить</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Вы еще не завершили ни одного урока.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 