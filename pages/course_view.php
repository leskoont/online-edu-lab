<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Проверка доступа к курсу
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM enrollments 
    WHERE account_id = :user_id 
    AND course_id = :course_id
");
$stmt->execute([
    ':user_id' => $userId,
    ':course_id' => $courseId
]);

$hasAccess = $stmt->fetchColumn() > 0;

if (!$hasAccess) {
    header('Location: student_dashboard.php');
    exit;
}

// Получение информации о курсе
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        e.progress,
        e.status,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name
    FROM 
        courses c
    JOIN 
        enrollments e ON c.course_id = e.course_id AND e.account_id = :user_id
    JOIN 
        teachers t ON c.teacher_id = t.teacher_id
    WHERE 
        c.course_id = :course_id
");
$stmt->execute([
    ':user_id' => $userId,
    ':course_id' => $courseId
]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: student_dashboard.php');
    exit;
}

// Получение списка уроков курса с прогрессом студента
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        COALESCE(lp.status, 'not_started') as progress_status,
        lp.completed_at,
        lp.score
    FROM 
        lessons l
    LEFT JOIN 
        lesson_progress lp ON l.lesson_id = lp.lesson_id AND lp.account_id = :user_id
    WHERE 
        l.course_id = :course_id
    ORDER BY 
        l.order_number
");
$stmt->execute([
    ':user_id' => $userId,
    ':course_id' => $courseId
]);
$lessons = $stmt->fetchAll();

// Обработка действия "Отметить курс как завершенный"
if (isset($_POST['complete_course']) && $course['status'] === 'active') {
    $stmt = $pdo->prepare("
        UPDATE enrollments
        SET status = 'completed', progress = 100
        WHERE account_id = :user_id AND course_id = :course_id
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':course_id' => $courseId
    ]);
    
    // Обновление страницы для отображения изменений
    header('Location: course_view.php?id=' . $courseId);
    exit;
}

// Проверка, все ли уроки пройдены
$allLessonsCompleted = true;
foreach ($lessons as $lesson) {
    if ($lesson['progress_status'] !== 'completed') {
        $allLessonsCompleted = false;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> | Платформа онлайн образования</title>
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
        h1, h2, h3 {
            color: #2c3e50;
        }
        .course-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .course-details {
            flex: 1;
        }
        .course-progress {
            width: 300px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .progress-container {
            width: 100%;
            background-color: #f1f1f1;
            border-radius: 3px;
            height: 20px;
            margin-top: 10px;
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
        .badge-not_started {
            background-color: #95a5a6;
        }
        .badge-in_progress {
            background-color: #f39c12;
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
            margin-top: 10px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #34495e;
        }
        .btn-success {
            background-color: #27ae60;
        }
        .btn-success:hover {
            background-color: #2ecc71;
        }
        .btn-disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        .btn-disabled:hover {
            background-color: #95a5a6;
        }
        .lesson-list {
            list-style-type: none;
            padding: 0;
        }
        .lesson-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .lesson-item:hover {
            background-color: #ecf0f1;
        }
        .lesson-info {
            flex: 1;
        }
        .lesson-title {
            font-weight: bold;
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .lesson-details {
            font-size: 13px;
            color: #7f8c8d;
        }
        .level-badge {
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .level-beginner {
            background-color: #3498db;
            color: white;
        }
        .level-intermediate {
            background-color: #f39c12;
            color: white;
        }
        .level-advanced {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <h1>Курс: <?php echo htmlspecialchars($course['title']); ?></h1>
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
        <div class="section">
            <div class="course-info">
                <div class="course-details">
                    <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                    <p><strong>Преподаватель:</strong> <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                    <p><strong>Категория:</strong> <?php echo htmlspecialchars($course['category']); ?></p>
                    <p><strong>Уровень:</strong> 
                        <span class="level-badge level-<?php echo $course['level']; ?>">
                            <?php 
                                switch ($course['level']) {
                                    case 'beginner': echo 'Начальный'; break;
                                    case 'intermediate': echo 'Средний'; break;
                                    case 'advanced': echo 'Продвинутый'; break;
                                    default: echo $course['level'];
                                }
                            ?>
                        </span>
                    </p>
                    <p><strong>Описание:</strong> <?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                </div>
                <div class="course-progress">
                    <h3>Ваш прогресс</h3>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $course['progress']; ?>%">
                            <?php echo round($course['progress']); ?>%
                        </div>
                    </div>
                    <p>
                        <strong>Статус:</strong> 
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
                    </p>
                    <p><strong>Пройдено уроков:</strong> 
                        <?php 
                            $completedLessons = 0;
                            foreach ($lessons as $lesson) {
                                if ($lesson['progress_status'] === 'completed') {
                                    $completedLessons++;
                                }
                            }
                            echo $completedLessons . ' из ' . count($lessons); 
                        ?>
                    </p>
                    
                    <?php if ($course['status'] === 'active'): ?>
                        <form method="POST" action="">
                            <button type="submit" name="complete_course" class="btn btn-success" <?php if (!$allLessonsCompleted) echo 'disabled title="Сначала пройдите все уроки" class="btn-disabled"'; ?>>
                                Отметить курс как завершенный
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Содержание курса</h2>
            
            <?php if (count($lessons) > 0): ?>
                <ul class="lesson-list">
                    <?php foreach ($lessons as $index => $lesson): ?>
                    <li class="lesson-item">
                        <div class="lesson-info">
                            <div class="lesson-title">
                                Урок <?php echo $index + 1; ?>: <?php echo htmlspecialchars($lesson['title']); ?>
                            </div>
                            <div class="lesson-details">
                                <span>
                                    <strong>Тип:</strong> 
                                    <?php 
                                        switch ($lesson['type']) {
                                            case 'video': echo 'Видео'; break;
                                            case 'text': echo 'Текст'; break;
                                            case 'quiz': echo 'Тест'; break;
                                            case 'assignment': echo 'Задание'; break;
                                            default: echo $lesson['type'];
                                        }
                                    ?>
                                </span>
                                <?php if ($lesson['duration'] > 0): ?>
                                    <span> | <strong>Длительность:</strong> <?php echo $lesson['duration']; ?> мин.</span>
                                <?php endif; ?>
                                <span> | <strong>Статус:</strong> 
                                    <span class="badge badge-<?php echo $lesson['progress_status']; ?>">
                                        <?php 
                                            switch ($lesson['progress_status']) {
                                                case 'completed': echo 'Завершен'; break;
                                                case 'in_progress': echo 'В процессе'; break;
                                                case 'not_started': echo 'Не начат'; break;
                                                default: echo $lesson['progress_status'];
                                            }
                                        ?>
                                    </span>
                                </span>
                                <?php if ($lesson['progress_status'] === 'completed' && $lesson['score'] !== null): ?>
                                    <span> | <strong>Оценка:</strong> <?php echo $lesson['score']; ?>/100</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <a href="lesson_view.php?id=<?php echo $lesson['lesson_id']; ?>" class="btn">
                                <?php 
                                    switch ($lesson['progress_status']) {
                                        case 'completed': echo 'Повторить'; break;
                                        case 'in_progress': echo 'Продолжить'; break;
                                        case 'not_started': echo 'Начать'; break;
                                        default: echo 'Открыть';
                                    }
                                ?>
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>В этом курсе пока нет уроков.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 