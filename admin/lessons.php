<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Получение действия из запроса
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Функция для проверки наличия урока по ID
function lessonExists($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE lesson_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// Функция для проверки наличия курса по ID
function courseExists($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// Обработка сообщений
$message = '';
$messageType = '';

// Проверка на фильтр по курсу
if ($course_id > 0 && !courseExists($pdo, $course_id)) {
    $message = 'Указанный курс не найден';
    $messageType = 'error';
    $course_id = 0;
}

// Получение информации о курсе, если фильтруем по курсу
$course = null;
if ($course_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            c.course_id, 
            c.title,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM 
            courses c
        JOIN
            teachers t ON c.teacher_id = t.teacher_id
        WHERE 
            c.course_id = :course_id
    ");
    $stmt->execute([':course_id' => $course_id]);
    $course = $stmt->fetch();
}

// Обработка различных действий
switch ($action) {
    case 'add':
        // Получение списка курсов для формы
        if ($course_id > 0) {
            // Если уже есть ID курса, получаем только его
            $stmt = $pdo->prepare("
                SELECT 
                    c.course_id, 
                    c.title
                FROM 
                    courses c
                WHERE 
                    c.course_id = :course_id
            ");
            $stmt->execute([':course_id' => $course_id]);
        } else {
            // Иначе получаем все курсы
            $stmt = $pdo->query("
                SELECT 
                    c.course_id, 
                    c.title
                FROM 
                    courses c
                ORDER BY 
                    c.title
            ");
        }
        $courses = $stmt->fetchAll();
        
        // Обработка добавления нового урока
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $lessonCourseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
            $orderNumber = isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0;
            $type = isset($_POST['type']) ? $_POST['type'] : 'video';
            
            // Проверка заполнения обязательных полей
            if (empty($title) || $lessonCourseId <= 0) {
                $message = 'Заполните все обязательные поля';
                $messageType = 'error';
            } else {
                // Добавление нового урока
                $stmt = $pdo->prepare("
                    INSERT INTO lessons (course_id, title, content, duration, order_number, type) 
                    VALUES (:course_id, :title, :content, :duration, :order_number, :type)
                ");
                
                $result = $stmt->execute([
                    ':course_id' => $lessonCourseId,
                    ':title' => $title,
                    ':content' => $content,
                    ':duration' => $duration,
                    ':order_number' => $orderNumber,
                    ':type' => $type
                ]);
                
                if ($result) {
                    $message = 'Урок успешно создан';
                    $messageType = 'success';
                    
                    // Перенаправление на список уроков
                    $redirectUrl = 'lessons.php?message=Урок успешно создан&messageType=success';
                    if ($lessonCourseId > 0) {
                        $redirectUrl .= '&course_id=' . $lessonCourseId;
                    }
                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    $message = 'Ошибка при создании урока';
                    $messageType = 'error';
                }
            }
        }
        break;
        
    case 'edit':
        // Проверка существования урока
        if ($id <= 0 || !lessonExists($pdo, $id)) {
            $message = 'Урок не найден';
            $messageType = 'error';
            $action = 'list'; // Возвращаемся к списку
        } else {
            // Получение данных урока для формы редактирования
            $stmt = $pdo->prepare("SELECT * FROM lessons WHERE lesson_id = :id");
            $stmt->execute([':id' => $id]);
            $lesson = $stmt->fetch();
            
            // Получение списка курсов для формы
            $stmt = $pdo->query("
                SELECT 
                    c.course_id, 
                    c.title
                FROM 
                    courses c
                ORDER BY 
                    c.title
            ");
            $courses = $stmt->fetchAll();
            
            // Обработка обновления урока
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? trim($_POST['content']) : '';
                $lessonCourseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
                $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
                $orderNumber = isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0;
                $type = isset($_POST['type']) ? $_POST['type'] : 'video';
                
                // Проверка заполнения обязательных полей
                if (empty($title) || $lessonCourseId <= 0) {
                    $message = 'Заполните все обязательные поля';
                    $messageType = 'error';
                } else {
                    // Обновление урока
                    $stmt = $pdo->prepare("
                        UPDATE lessons 
                        SET 
                            course_id = :course_id,
                            title = :title,
                            content = :content,
                            duration = :duration,
                            order_number = :order_number,
                            type = :type
                        WHERE 
                            lesson_id = :id
                    ");
                    
                    $result = $stmt->execute([
                        ':id' => $id,
                        ':course_id' => $lessonCourseId,
                        ':title' => $title,
                        ':content' => $content,
                        ':duration' => $duration,
                        ':order_number' => $orderNumber,
                        ':type' => $type
                    ]);
                    
                    if ($result) {
                        $message = 'Урок успешно обновлен';
                        $messageType = 'success';
                        
                        // Перенаправление на список уроков
                        $redirectUrl = 'lessons.php?message=Урок успешно обновлен&messageType=success';
                        if ($course_id > 0) {
                            $redirectUrl .= '&course_id=' . $course_id;
                        }
                        header('Location: ' . $redirectUrl);
                        exit;
                    } else {
                        $message = 'Ошибка при обновлении урока';
                        $messageType = 'error';
                    }
                }
            }
        }
        break;
        
    case 'delete':
        // Проверка существования урока
        if ($id <= 0 || !lessonExists($pdo, $id)) {
            $message = 'Урок не найден';
            $messageType = 'error';
        } else {
            // Получение ID курса перед удалением (для перенаправления)
            $stmt = $pdo->prepare("SELECT course_id FROM lessons WHERE lesson_id = :id");
            $stmt->execute([':id' => $id]);
            $lessonCourseId = $stmt->fetchColumn();
            
            // Начинаем транзакцию для безопасного удаления связанных данных
            $pdo->beginTransaction();
            
            try {
                // Удаление связанных записей в lesson_progress
                $stmt = $pdo->prepare("DELETE FROM lesson_progress WHERE lesson_id = :id");
                $stmt->execute([':id' => $id]);
                
                // Удаление самого урока
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE lesson_id = :id");
                $stmt->execute([':id' => $id]);
                
                // Фиксируем транзакцию
                $pdo->commit();
                
                $message = 'Урок успешно удален';
                $messageType = 'success';
            } catch (Exception $e) {
                // Откат транзакции в случае ошибки
                $pdo->rollBack();
                
                $message = 'Ошибка при удалении урока: ' . $e->getMessage();
                $messageType = 'error';
            }
            
            // После удаления перенаправляем на список уроков
            $redirectUrl = 'lessons.php?message=' . urlencode($message) . '&messageType=' . $messageType;
            if ($course_id > 0 || $lessonCourseId > 0) {
                $redirectUrl .= '&course_id=' . ($course_id > 0 ? $course_id : $lessonCourseId);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
        break;
        
    case 'list':
    default:
        // Обработка GET-параметров для списка
        $message = isset($_GET['message']) ? $_GET['message'] : $message;
        $messageType = isset($_GET['messageType']) ? $_GET['messageType'] : $messageType;
        
        // Получение списка курсов для фильтрации
        $stmt = $pdo->query("
            SELECT 
                c.course_id, 
                c.title,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) AS lesson_count
            FROM 
                courses c
            ORDER BY 
                c.title
        ");
        $filterCourses = $stmt->fetchAll();
        
        // Формирование SQL запроса с учетом фильтра по курсу
        $sql = "
            SELECT 
                l.lesson_id,
                l.title,
                l.duration,
                l.order_number,
                l.type,
                c.course_id,
                c.title AS course_title,
                (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.lesson_id = l.lesson_id) AS progress_count
            FROM 
                lessons l
            JOIN 
                courses c ON l.course_id = c.course_id
        ";
        
        $params = [];
        if ($course_id > 0) {
            $sql .= " WHERE l.course_id = :course_id";
            $params[':course_id'] = $course_id;
        }
        
        $sql .= " ORDER BY c.title, l.order_number";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $lessons = $stmt->fetchAll();
        break;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление уроками | Админ-панель</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding-top: 20px;
        }
        .sidebar-header {
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid #34495e;
        }
        .sidebar-menu {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            padding: 0;
        }
        .sidebar-menu a {
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            padding: 15px 20px;
            transition: background-color 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #34495e;
        }
        .sidebar-menu i {
            margin-right: 10px;
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #34495e;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            transition: background-color 0.3s;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .filter-box {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .filter-box label {
            margin-right: 10px;
            font-weight: bold;
        }
        .filter-box select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        .course-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px;
        }
        .badge-video {
            background-color: #3498db;
            color: white;
        }
        .badge-text {
            background-color: #27ae60;
            color: white;
        }
        .badge-quiz {
            background-color: #f1c40f;
            color: black;
        }
        .badge-assignment {
            background-color: #9b59b6;
            color: white;
        }
        .actions {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        .form-row {
            display: flex;
            margin: 0 -10px;
        }
        .form-col {
            flex: 1;
            padding: 0 10px;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Админ-панель</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php">Дашборд</a></li>
                <li><a href="accounts.php">Управление аккаунтами</a></li>
                <li><a href="teachers.php">Управление преподавателями</a></li>
                <li><a href="courses.php">Управление курсами</a></li>
                <li><a href="lessons.php" class="active">Управление уроками</a></li>
                <li><a href="settings.php">Настройки</a></li>
                <li><a href="logout.php">Выход</a></li>
                <li><a href="../index.php">Вернуться на сайт</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>
                    <?php 
                    if ($action === 'add') {
                        echo 'Добавление нового урока';
                    } elseif ($action === 'edit') {
                        echo 'Редактирование урока';
                    } else {
                        echo 'Управление уроками';
                        if ($course) {
                            echo ' курса "' . htmlspecialchars($course['title']) . '"';
                        }
                    }
                    ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                <div>
                    <?php if ($course_id > 0): ?>
                    <a href="lessons.php" class="btn">Все уроки</a>
                    <?php endif; ?>
                    <a href="?action=add<?php echo $course_id > 0 ? '&course_id=' . $course_id : ''; ?>" class="btn">Добавить урок</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <?php if ($course): ?>
                <div class="course-info">
                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    <p>Преподаватель: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                    <p>Всего уроков: <?php echo count($lessons); ?></p>
                    <div>
                        <a href="courses.php?action=edit&id=<?php echo $course['course_id']; ?>" class="btn">Редактировать курс</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="filter-box">
                    <label for="filter-course">Фильтр по курсу:</label>
                    <select id="filter-course" onchange="window.location.href='lessons.php?course_id='+this.value">
                        <option value="">Все курсы</option>
                        <?php foreach ($filterCourses as $filterCourse): ?>
                        <option value="<?php echo $filterCourse['course_id']; ?>" <?php echo $course_id == $filterCourse['course_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($filterCourse['title']) . ' (' . $filterCourse['lesson_count'] . ' уроков)'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Список уроков -->
                <div class="card">
                    <?php if (count($lessons) > 0): ?>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Курс</th>
                            <th>Тип</th>
                            <th>Длительность</th>
                            <th>Порядок</th>
                            <th>Прогресс</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td><?php echo $lesson['lesson_id']; ?></td>
                            <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td>
                                <?php if (!$course): ?>
                                <a href="?course_id=<?php echo $lesson['course_id']; ?>">
                                    <?php echo htmlspecialchars($lesson['course_title']); ?>
                                </a>
                                <?php else: ?>
                                <?php echo htmlspecialchars($lesson['course_title']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $badgeClass = '';
                                $typeText = '';
                                switch($lesson['type']) {
                                    case 'video': 
                                        $badgeClass = 'badge-video';
                                        $typeText = 'Видео';
                                        break;
                                    case 'text': 
                                        $badgeClass = 'badge-text';
                                        $typeText = 'Текст';
                                        break;
                                    case 'quiz': 
                                        $badgeClass = 'badge-quiz';
                                        $typeText = 'Тест';
                                        break;
                                    case 'assignment': 
                                        $badgeClass = 'badge-assignment';
                                        $typeText = 'Задание';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $typeText; ?></span>
                            </td>
                            <td><?php echo $lesson['duration'] ? $lesson['duration'] . ' мин.' : 'Н/Д'; ?></td>
                            <td><?php echo $lesson['order_number']; ?></td>
                            <td><?php echo $lesson['progress_count']; ?> студентов</td>
                            <td>
                                <a href="?action=edit&id=<?php echo $lesson['lesson_id']; ?><?php echo $course_id > 0 ? '&course_id=' . $course_id : ''; ?>" class="btn">Редактировать</a>
                                <a href="?action=delete&id=<?php echo $lesson['lesson_id']; ?><?php echo $course_id > 0 ? '&course_id=' . $course_id : ''; ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот урок?')">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php else: ?>
                    <p>Уроки не найдены.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Форма добавления/редактирования урока -->
                <div class="card">
                    <form method="post">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="title">Название урока *</label>
                                    <input type="text" id="title" name="title" value="<?php echo isset($lesson) ? htmlspecialchars($lesson['title']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="course_id">Курс *</label>
                                    <select id="course_id" name="course_id" required <?php echo ($course_id > 0 && $action === 'add') ? 'disabled' : ''; ?>>
                                        <option value="">Выберите курс</option>
                                        <?php foreach ($courses as $courseOption): ?>
                                        <option value="<?php echo $courseOption['course_id']; ?>" 
                                            <?php 
                                            if (isset($lesson) && $lesson['course_id'] == $courseOption['course_id']) {
                                                echo 'selected';
                                            } elseif ($course_id > 0 && $action === 'add' && $course_id == $courseOption['course_id']) {
                                                echo 'selected';
                                            }
                                            ?>
                                        >
                                            <?php echo htmlspecialchars($courseOption['title']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($course_id > 0 && $action === 'add'): ?>
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">Содержание урока</label>
                            <textarea id="content" name="content"><?php echo isset($lesson) ? htmlspecialchars($lesson['content']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="type">Тип урока</label>
                                    <select id="type" name="type">
                                        <option value="video" <?php echo (isset($lesson) && $lesson['type'] === 'video') ? 'selected' : ''; ?>>Видео</option>
                                        <option value="text" <?php echo (isset($lesson) && $lesson['type'] === 'text') ? 'selected' : ''; ?>>Текст</option>
                                        <option value="quiz" <?php echo (isset($lesson) && $lesson['type'] === 'quiz') ? 'selected' : ''; ?>>Тест</option>
                                        <option value="assignment" <?php echo (isset($lesson) && $lesson['type'] === 'assignment') ? 'selected' : ''; ?>>Задание</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="duration">Длительность (в минутах)</label>
                                    <input type="number" id="duration" name="duration" min="0" value="<?php echo isset($lesson) ? $lesson['duration'] : '0'; ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="order_number">Порядковый номер</label>
                                    <input type="number" id="order_number" name="order_number" min="1" value="<?php echo isset($lesson) ? $lesson['order_number'] : '1'; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <button type="submit" class="btn">Сохранить</button>
                            <a href="lessons.php<?php echo $course_id > 0 ? '?course_id=' . $course_id : ''; ?>" class="btn">Отмена</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 