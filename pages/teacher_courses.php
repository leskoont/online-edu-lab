<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: teacher_login.php');
    exit;
}

// Получение ID преподавателя
$teacherId = $_SESSION['teacher_id'];

// Обработка действий
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Проверка существования курса и принадлежности преподавателю
function checkCourseOwnership($pdo, $courseId, $teacherId) {
    if ($courseId <= 0) return false;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM courses 
        WHERE course_id = :course_id AND teacher_id = :teacher_id
    ");
    $stmt->execute([
        ':course_id' => $courseId,
        ':teacher_id' => $teacherId
    ]);
    
    return $stmt->fetchColumn() > 0;
}

// Обработка сообщений
$message = '';
$messageType = '';

// Обработка различных действий
switch ($action) {
    case 'add':
        // Обработка добавления нового курса
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $category = isset($_POST['category']) ? trim($_POST['category']) : '';
            $level = isset($_POST['level']) ? $_POST['level'] : 'beginner';
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
            $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
            
            // Проверка заполнения обязательных полей
            if (empty($title)) {
                $message = 'Заполните все обязательные поля';
                $messageType = 'error';
            } else {
                // Добавление нового курса
                $stmt = $pdo->prepare("
                    INSERT INTO courses (
                        teacher_id, title, description, category, level, price, created_at, status
                    ) VALUES (
                        :teacher_id, :title, :description, :category, :level, :price, NOW(), :status
                    )
                ");
                
                $result = $stmt->execute([
                    ':teacher_id' => $teacherId,
                    ':title' => $title,
                    ':description' => $description,
                    ':category' => $category,
                    ':level' => $level,
                    ':price' => $price,
                    ':status' => $status
                ]);
                
                if ($result) {
                    $message = 'Курс успешно создан';
                    $messageType = 'success';
                    
                    // Перенаправление на список курсов
                    header('Location: teacher_courses.php?message=' . urlencode($message) . '&messageType=' . $messageType);
                    exit;
                } else {
                    $message = 'Ошибка при создании курса';
                    $messageType = 'error';
                }
            }
        }
        break;
        
    case 'edit':
        // Проверка существования курса и принадлежности преподавателю
        if (!checkCourseOwnership($pdo, $courseId, $teacherId)) {
            $message = 'Курс не найден или вы не имеете прав на его редактирование';
            $messageType = 'error';
            $action = 'list'; // Возвращаемся к списку
        } else {
            // Обработка обновления курса
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $description = isset($_POST['description']) ? trim($_POST['description']) : '';
                $category = isset($_POST['category']) ? trim($_POST['category']) : '';
                $level = isset($_POST['level']) ? $_POST['level'] : 'beginner';
                $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
                $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
                
                // Проверка заполнения обязательных полей
                if (empty($title)) {
                    $message = 'Заполните все обязательные поля';
                    $messageType = 'error';
                } else {
                    // Обновление курса
                    $stmt = $pdo->prepare("
                        UPDATE courses 
                        SET 
                            title = :title,
                            description = :description,
                            category = :category,
                            level = :level,
                            price = :price,
                            status = :status
                        WHERE 
                            course_id = :id AND teacher_id = :teacher_id
                    ");
                    
                    $result = $stmt->execute([
                        ':id' => $courseId,
                        ':teacher_id' => $teacherId,
                        ':title' => $title,
                        ':description' => $description,
                        ':category' => $category,
                        ':level' => $level,
                        ':price' => $price,
                        ':status' => $status
                    ]);
                    
                    if ($result) {
                        $message = 'Курс успешно обновлен';
                        $messageType = 'success';
                        
                        // Перенаправление на список курсов
                        header('Location: teacher_courses.php?message=' . urlencode($message) . '&messageType=' . $messageType);
                        exit;
                    } else {
                        $message = 'Ошибка при обновлении курса';
                        $messageType = 'error';
                    }
                }
            }
            
            // Получение данных курса для формы редактирования
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = :id AND teacher_id = :teacher_id");
            $stmt->execute([
                ':id' => $courseId,
                ':teacher_id' => $teacherId
            ]);
            $course = $stmt->fetch();
        }
        break;
        
    case 'delete':
        // Проверка существования курса и принадлежности преподавателю
        if (!checkCourseOwnership($pdo, $courseId, $teacherId)) {
            $message = 'Курс не найден или вы не имеете прав на его удаление';
            $messageType = 'error';
        } else {
            // Удаление курса
            $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = :id AND teacher_id = :teacher_id");
            $result = $stmt->execute([
                ':id' => $courseId,
                ':teacher_id' => $teacherId
            ]);
            
            if ($result) {
                $message = 'Курс успешно удален';
                $messageType = 'success';
            } else {
                $message = 'Ошибка при удалении курса';
                $messageType = 'error';
            }
        }
        
        // После удаления перенаправляем на список курсов
        header('Location: teacher_courses.php?message=' . urlencode($message) . '&messageType=' . $messageType);
        exit;
        break;
        
    case 'lessons':
        // Проверка существования курса и принадлежности преподавателю
        if (!checkCourseOwnership($pdo, $courseId, $teacherId)) {
            $message = 'Курс не найден или вы не имеете прав на просмотр его уроков';
            $messageType = 'error';
            $action = 'list'; // Возвращаемся к списку
        } else {
            // Получение информации о курсе
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = :id AND teacher_id = :teacher_id");
            $stmt->execute([
                ':id' => $courseId,
                ':teacher_id' => $teacherId
            ]);
            $course = $stmt->fetch();
            
            // Получение списка уроков курса
            $stmt = $pdo->prepare("
                SELECT * FROM lessons 
                WHERE course_id = :course_id 
                ORDER BY order_number
            ");
            $stmt->execute([':course_id' => $courseId]);
            $lessons = $stmt->fetchAll();
        }
        break;
        
    case 'list':
    default:
        // Получение сообщения из GET-параметров
        if (isset($_GET['message'])) {
            $message = $_GET['message'];
            $messageType = isset($_GET['messageType']) ? $_GET['messageType'] : 'info';
        }
        
        // Получение списка курсов преподавателя
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) AS lesson_count,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) AS student_count
            FROM 
                courses c
            WHERE 
                c.teacher_id = :teacher_id
            ORDER BY 
                c.created_at DESC
        ");
        $stmt->execute([':teacher_id' => $teacherId]);
        $courses = $stmt->fetchAll();
        break;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои курсы | Платформа онлайн образования</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
        }
        .btn {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 3px;
            text-decoration: none;
            color: white;
            transition: background-color 0.3s;
            margin-right: 5px;
        }
        .btn-primary {
            background-color: #2c3e50;
        }
        .btn-primary:hover {
            background-color: #34495e;
        }
        .btn-success {
            background-color: #27ae60;
        }
        .btn-success:hover {
            background-color: #2ecc71;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
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
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        textarea {
            height: 150px;
            resize: vertical;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
        }
        .message.success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .message.error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 10px;
            color: white;
        }
        .badge-draft {
            background-color: #95a5a6;
        }
        .badge-published {
            background-color: #2ecc71;
        }
        .badge-archived {
            background-color: #7f8c8d;
        }
    </style>
</head>
<body>
    <header>
        <h1>Мои курсы</h1>
    </header>
    <nav>
        <ul>
            <li><a href="teacher_dashboard.php">Панель</a></li>
            <li><a href="teacher_courses.php">Мои курсы</a></li>
            <li><a href="../index.php">На главную</a></li>
        </ul>
    </nav>
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <!-- Список курсов -->
            <div class="section">
                <h2>Мои курсы</h2>
                <a href="?action=add" class="btn btn-primary">Добавить новый курс</a>
                
                <?php if (count($courses) > 0): ?>
                    <table>
                        <tr>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Уровень</th>
                            <th>Цена</th>
                            <th>Статус</th>
                            <th>Уроки</th>
                            <th>Студенты</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['title']); ?></td>
                            <td><?php echo htmlspecialchars($course['category']); ?></td>
                            <td><?php echo htmlspecialchars($course['level']); ?></td>
                            <td><?php echo number_format($course['price'], 2, '.', ' '); ?> ₽</td>
                            <td>
                                <span class="badge badge-<?php echo $course['status']; ?>">
                                    <?php 
                                        switch ($course['status']) {
                                            case 'draft': echo 'Черновик'; break;
                                            case 'published': echo 'Опубликован'; break;
                                            case 'archived': echo 'Архив'; break;
                                            default: echo $course['status'];
                                        }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $course['lesson_count']; ?></td>
                            <td><?php echo $course['student_count']; ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $course['course_id']; ?>" class="btn btn-primary">Редактировать</a>
                                <a href="?action=lessons&id=<?php echo $course['course_id']; ?>" class="btn btn-success">Уроки</a>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $course['course_id']; ?>)" class="btn btn-danger">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>У вас пока нет созданных курсов.</p>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action === 'add'): ?>
            <!-- Форма добавления курса -->
            <div class="section">
                <h2>Добавление нового курса</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title">Название курса *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Категория</label>
                        <input type="text" id="category" name="category">
                    </div>
                    
                    <div class="form-group">
                        <label for="level">Уровень сложности</label>
                        <select id="level" name="level">
                            <option value="beginner">Начальный</option>
                            <option value="intermediate">Средний</option>
                            <option value="advanced">Продвинутый</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Цена (₽)</label>
                        <input type="number" id="price" name="price" min="0" step="0.01" value="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Статус</label>
                        <select id="status" name="status">
                            <option value="draft">Черновик</option>
                            <option value="published">Опубликован</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Создать курс</button>
                        <a href="?action=list" class="btn btn-danger">Отмена</a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action === 'edit' && isset($course)): ?>
            <!-- Форма редактирования курса -->
            <div class="section">
                <h2>Редактирование курса</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title">Название курса *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Категория</label>
                        <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($course['category']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="level">Уровень сложности</label>
                        <select id="level" name="level">
                            <option value="beginner" <?php if ($course['level'] === 'beginner') echo 'selected'; ?>>Начальный</option>
                            <option value="intermediate" <?php if ($course['level'] === 'intermediate') echo 'selected'; ?>>Средний</option>
                            <option value="advanced" <?php if ($course['level'] === 'advanced') echo 'selected'; ?>>Продвинутый</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Цена (₽)</label>
                        <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo number_format($course['price'], 2, '.', ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Статус</label>
                        <select id="status" name="status">
                            <option value="draft" <?php if ($course['status'] === 'draft') echo 'selected'; ?>>Черновик</option>
                            <option value="published" <?php if ($course['status'] === 'published') echo 'selected'; ?>>Опубликован</option>
                            <option value="archived" <?php if ($course['status'] === 'archived') echo 'selected'; ?>>Архив</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        <a href="?action=list" class="btn btn-danger">Отмена</a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action === 'lessons' && isset($course)): ?>
            <!-- Список уроков курса -->
            <div class="section">
                <h2>Уроки курса: <?php echo htmlspecialchars($course['title']); ?></h2>
                <a href="teacher_lessons.php?action=add&course_id=<?php echo $course['course_id']; ?>" class="btn btn-primary">Добавить урок</a>
                <a href="?action=list" class="btn btn-danger">Назад к курсам</a>
                
                <?php if (count($lessons) > 0): ?>
                    <table>
                        <tr>
                            <th>№</th>
                            <th>Название</th>
                            <th>Тип</th>
                            <th>Длительность (мин)</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td><?php echo $lesson['order_number']; ?></td>
                            <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td>
                                <?php 
                                    switch ($lesson['type']) {
                                        case 'video': echo 'Видео'; break;
                                        case 'text': echo 'Текст'; break;
                                        case 'quiz': echo 'Тест'; break;
                                        case 'assignment': echo 'Задание'; break;
                                        default: echo $lesson['type'];
                                    }
                                ?>
                            </td>
                            <td><?php echo $lesson['duration']; ?></td>
                            <td>
                                <a href="teacher_lessons.php?action=edit&id=<?php echo $lesson['lesson_id']; ?>" class="btn btn-primary">Редактировать</a>
                                <a href="javascript:void(0);" onclick="confirmDeleteLesson(<?php echo $lesson['lesson_id']; ?>)" class="btn btn-danger">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>У этого курса пока нет уроков.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmDelete(id) {
            if (confirm('Вы действительно хотите удалить этот курс? Все связанные данные (уроки, записи студентов) также будут удалены.')) {
                window.location.href = '?action=delete&id=' + id;
            }
        }
        
        function confirmDeleteLesson(id) {
            if (confirm('Вы действительно хотите удалить этот урок?')) {
                window.location.href = 'teacher_lessons.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html> 