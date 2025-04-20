<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получение информации об уроке и курсе
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        c.course_id,
        c.title as course_title,
        c.teacher_id,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        (SELECT COUNT(*) FROM enrollments WHERE account_id = :user_id AND course_id = c.course_id) as is_enrolled
    FROM 
        lessons l
    JOIN 
        courses c ON l.course_id = c.course_id
    JOIN 
        teachers t ON c.teacher_id = t.teacher_id
    WHERE 
        l.lesson_id = :lesson_id
");
$stmt->execute([
    ':user_id' => $userId,
    ':lesson_id' => $lessonId
]);
$lesson = $stmt->fetch();

// Проверка существования урока и доступа к нему
if (!$lesson || $lesson['is_enrolled'] == 0) {
    header('Location: student_dashboard.php');
    exit;
}

// Получение прогресса по уроку
$stmt = $pdo->prepare("
    SELECT * 
    FROM lesson_progress 
    WHERE account_id = :user_id AND lesson_id = :lesson_id
");
$stmt->execute([
    ':user_id' => $userId,
    ':lesson_id' => $lessonId
]);
$progress = $stmt->fetch();

// Если это первое посещение урока, создать запись о прогрессе
if (!$progress) {
    $stmt = $pdo->prepare("
        INSERT INTO lesson_progress (account_id, lesson_id, status, completed_at, score)
        VALUES (:user_id, :lesson_id, 'in_progress', NULL, NULL)
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':lesson_id' => $lessonId
    ]);
    
    $progress = [
        'status' => 'in_progress',
        'completed_at' => null,
        'score' => null
    ];
} elseif ($progress['status'] === 'not_started') {
    // Обновление статуса, если был "не начат"
    $stmt = $pdo->prepare("
        UPDATE lesson_progress 
        SET status = 'in_progress' 
        WHERE account_id = :user_id AND lesson_id = :lesson_id
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':lesson_id' => $lessonId
    ]);
    
    $progress['status'] = 'in_progress';
}

// Обработка действий пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['complete_lesson'])) {
        // Отметить урок как пройденный
        $stmt = $pdo->prepare("
            UPDATE lesson_progress 
            SET status = 'completed', completed_at = NOW()
            WHERE account_id = :user_id AND lesson_id = :lesson_id
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId
        ]);
        
        // Обновление прогресса по курсу
        updateCourseProgress($pdo, $userId, $lesson['course_id']);
        
        // Перенаправление, чтобы избежать повторной отправки формы
        header('Location: lesson_view.php?id=' . $lessonId . '&completed=true');
        exit;
    } elseif (isset($_POST['submit_quiz']) && $lesson['type'] === 'quiz') {
        // Обработка отправки теста
        $score = isset($_POST['quiz_result']) ? (int)$_POST['quiz_result'] : 0;
        
        $stmt = $pdo->prepare("
            UPDATE lesson_progress 
            SET status = 'completed', completed_at = NOW(), score = :score
            WHERE account_id = :user_id AND lesson_id = :lesson_id
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':lesson_id' => $lessonId,
            ':score' => $score
        ]);
        
        // Обновление прогресса по курсу
        updateCourseProgress($pdo, $userId, $lesson['course_id']);
        
        // Перенаправление, чтобы избежать повторной отправки формы
        header('Location: lesson_view.php?id=' . $lessonId . '&completed=true&score=' . $score);
        exit;
    }
}

// Функция для обновления прогресса по курсу
function updateCourseProgress($pdo, $userId, $courseId) {
    // Получаем общее количество уроков в курсе
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lessons 
        WHERE course_id = :course_id
    ");
    $stmt->execute([':course_id' => $courseId]);
    $totalLessons = $stmt->fetchColumn();
    
    if ($totalLessons === 0) return;
    
    // Получаем количество завершенных уроков
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lesson_progress lp
        JOIN lessons l ON lp.lesson_id = l.lesson_id
        WHERE lp.account_id = :user_id 
        AND l.course_id = :course_id 
        AND lp.status = 'completed'
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':course_id' => $courseId
    ]);
    $completedLessons = $stmt->fetchColumn();
    
    // Рассчитываем прогресс
    $progress = ($completedLessons / $totalLessons) * 100;
    
    // Обновляем запись о прогрессе
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET progress = :progress
        WHERE account_id = :user_id AND course_id = :course_id
    ");
    $stmt->execute([
        ':progress' => $progress,
        ':user_id' => $userId,
        ':course_id' => $courseId
    ]);
    
    // Если все уроки пройдены, предложим отметить курс как завершенный
    if ($completedLessons == $totalLessons) {
        return true;
    }
    return false;
}

// Получение предыдущего и следующего урока
$stmt = $pdo->prepare("
    SELECT lesson_id, title, order_number 
    FROM lessons 
    WHERE course_id = :course_id AND order_number < :current_order
    ORDER BY order_number DESC
    LIMIT 1
");
$stmt->execute([
    ':course_id' => $lesson['course_id'],
    ':current_order' => $lesson['order_number']
]);
$prevLesson = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT lesson_id, title, order_number 
    FROM lessons 
    WHERE course_id = :course_id AND order_number > :current_order
    ORDER BY order_number ASC
    LIMIT 1
");
$stmt->execute([
    ':course_id' => $lesson['course_id'],
    ':current_order' => $lesson['order_number']
]);
$nextLesson = $stmt->fetch();

// Сообщение о завершении
$completedMessage = '';
$showCompletedMessage = false;
if (isset($_GET['completed']) && $_GET['completed'] === 'true') {
    $showCompletedMessage = true;
    if (isset($_GET['score'])) {
        $score = (int)$_GET['score'];
        $completedMessage = 'Урок успешно пройден! Ваша оценка: ' . $score . '/100';
    } else {
        $completedMessage = 'Урок успешно пройден!';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> | Платформа онлайн образования</title>
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
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .course-info {
            font-size: 14px;
            color: #7f8c8d;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 10px;
            color: white;
        }
        .badge-video {
            background-color: #3498db;
        }
        .badge-text {
            background-color: #2ecc71;
        }
        .badge-quiz {
            background-color: #f39c12;
        }
        .badge-assignment {
            background-color: #e74c3c;
        }
        .badge-completed {
            background-color: #2ecc71;
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
        .navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .lesson-content {
            line-height: 1.6;
            font-size: 16px;
        }
        .lesson-video {
            text-align: center;
            margin: 20px 0;
        }
        .video-placeholder {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
        }
        .quiz-container {
            margin-top: 20px;
        }
        .quiz-question {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .quiz-options {
            list-style-type: none;
            padding: 0;
        }
        .quiz-options li {
            margin-bottom: 10px;
        }
        .quiz-options label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .quiz-options input[type="radio"] {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
    </header>
    <nav>
        <ul>
            <li><a href="student_dashboard.php">Главная</a></li>
            <li><a href="student_courses.php">Мои курсы</a></li>
            <li><a href="course_view.php?id=<?php echo $lesson['course_id']; ?>">К курсу</a></li>
            <li><a href="../index.php">На главную</a></li>
        </ul>
    </nav>
    <div class="container">
        <?php if ($showCompletedMessage): ?>
            <div class="message">
                <?php echo $completedMessage; ?>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <div class="lesson-header">
                <div>
                    <h2><?php echo htmlspecialchars($lesson['title']); ?></h2>
                    <div class="course-info">
                        Курс: <a href="course_view.php?id=<?php echo $lesson['course_id']; ?>"><?php echo htmlspecialchars($lesson['course_title']); ?></a> | 
                        Преподаватель: <?php echo htmlspecialchars($lesson['teacher_name']); ?> | 
                        Тип: 
                        <span class="badge badge-<?php echo $lesson['type']; ?>">
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
                        <?php if ($progress['status'] === 'completed'): ?>
                            | Статус: <span class="badge badge-completed">Завершен</span>
                        <?php elseif ($progress['status'] === 'in_progress'): ?>
                            | Статус: <span class="badge badge-in_progress">В процессе</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="lesson-content">
                <?php if ($lesson['type'] === 'video'): ?>
                    <!-- Видео урок -->
                    <div class="lesson-video">
                        <div class="video-placeholder">
                            <p>Здесь будет видео урока.</p>
                            <p>В реальном приложении здесь будет видеоплеер с контентом.</p>
                            <p>Длительность: <?php echo $lesson['duration']; ?> минут</p>
                        </div>
                    </div>
                    
                    <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                    
                    <?php if ($progress['status'] !== 'completed'): ?>
                        <form method="POST">
                            <button type="submit" name="complete_lesson" class="btn btn-success">Отметить урок как просмотренный</button>
                        </form>
                    <?php endif; ?>
                    
                <?php elseif ($lesson['type'] === 'text'): ?>
                    <!-- Текстовый урок -->
                    <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                    
                    <?php if ($progress['status'] !== 'completed'): ?>
                        <form method="POST">
                            <button type="submit" name="complete_lesson" class="btn btn-success">Отметить урок как прочитанный</button>
                        </form>
                    <?php endif; ?>
                    
                <?php elseif ($lesson['type'] === 'quiz'): ?>
                    <!-- Тест -->
                    <div class="quiz-container">
                        <p>Ответьте на следующие вопросы, чтобы проверить свои знания:</p>
                        
                        <!-- В реальном приложении вопросы будут загружаться из базы данных -->
                        <div class="quiz-question">
                            <h3>Вопрос 1:</h3>
                            <p>Пример вопроса для данного урока?</p>
                            <ul class="quiz-options">
                                <li><label><input type="radio" name="q1" value="a"> Вариант A</label></li>
                                <li><label><input type="radio" name="q1" value="b"> Вариант B</label></li>
                                <li><label><input type="radio" name="q1" value="c"> Вариант C</label></li>
                                <li><label><input type="radio" name="q1" value="d"> Вариант D</label></li>
                            </ul>
                        </div>
                        
                        <div class="quiz-question">
                            <h3>Вопрос 2:</h3>
                            <p>Еще один пример вопроса для проверки знаний?</p>
                            <ul class="quiz-options">
                                <li><label><input type="radio" name="q2" value="a"> Вариант A</label></li>
                                <li><label><input type="radio" name="q2" value="b"> Вариант B</label></li>
                                <li><label><input type="radio" name="q2" value="c"> Вариант C</label></li>
                                <li><label><input type="radio" name="q2" value="d"> Вариант D</label></li>
                            </ul>
                        </div>
                        
                        <?php if ($progress['status'] !== 'completed'): ?>
                            <form method="POST" id="quizForm">
                                <input type="hidden" name="quiz_result" id="quizResult" value="0">
                                <button type="button" onclick="calculateScore()" class="btn btn-success">Проверить ответы</button>
                            </form>
                            
                            <script>
                                function calculateScore() {
                                    // В реальном приложении будет реальная проверка ответов
                                    // Здесь просто имитация
                                    var score = Math.floor(Math.random() * 41) + 60; // Случайная оценка от 60 до 100
                                    document.getElementById('quizResult').value = score;
                                    if (confirm('Ваша оценка: ' + score + ' из 100. Подтвердить завершение теста?')) {
                                        document.getElementById('quizForm').submit();
                                    }
                                }
                            </script>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif ($lesson['type'] === 'assignment'): ?>
                    <!-- Задание -->
                    <div>
                        <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                        
                        <?php if ($progress['status'] !== 'completed'): ?>
                            <div style="margin-top: 20px;">
                                <h3>Отправка задания</h3>
                                <p>В реальном приложении здесь будет форма для загрузки файлов и отправки решения.</p>
                                <form method="POST">
                                    <button type="submit" name="complete_lesson" class="btn btn-success">Отметить задание как выполненное</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="navigation">
                <div>
                    <?php if ($prevLesson): ?>
                        <a href="lesson_view.php?id=<?php echo $prevLesson['lesson_id']; ?>" class="btn">← Предыдущий урок</a>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="course_view.php?id=<?php echo $lesson['course_id']; ?>" class="btn">К содержанию курса</a>
                </div>
                <div>
                    <?php if ($nextLesson): ?>
                        <a href="lesson_view.php?id=<?php echo $nextLesson['lesson_id']; ?>" class="btn">Следующий урок →</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 