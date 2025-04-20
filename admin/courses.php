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

// Функция для проверки наличия курса по ID
function courseExists($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// Обработка сообщений
$message = '';
$messageType = '';

// Обработка различных действий
switch ($action) {
    case 'add':
        // Получение списка преподавателей для формы
        $stmt = $pdo->query("
            SELECT 
                t.teacher_id, 
                CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM 
                teachers t
            JOIN
                accounts a ON t.account_id = a.account_id
            WHERE 
                a.role = 'teacher'
            ORDER BY 
                t.last_name, t.first_name
        ");
        $teachers = $stmt->fetchAll();
        
        // Обработка добавления нового курса
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
            $category = isset($_POST['category']) ? trim($_POST['category']) : '';
            $level = isset($_POST['level']) ? $_POST['level'] : 'beginner';
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
            $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
            
            // Проверка заполнения обязательных полей
            if (empty($title) || $teacher_id <= 0) {
                $message = 'Заполните все обязательные поля';
                $messageType = 'error';
            } else {
                // Добавление нового курса
                $stmt = $pdo->prepare("
                    INSERT INTO courses (teacher_id, title, description, category, level, price, created_at, status) 
                    VALUES (:teacher_id, :title, :description, :category, :level, :price, NOW(), :status)
                ");
                
                $result = $stmt->execute([
                    ':teacher_id' => $teacher_id,
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
                    header('Location: courses.php?message=Курс успешно создан&messageType=success');
                    exit;
                } else {
                    $message = 'Ошибка при создании курса';
                    $messageType = 'error';
                }
            }
        }
        break;
        
    case 'edit':
        // Проверка существования курса
        if ($id <= 0 || !courseExists($pdo, $id)) {
            $message = 'Курс не найден';
            $messageType = 'error';
            $action = 'list'; // Возвращаемся к списку
        } else {
            // Получение списка преподавателей для формы
            $stmt = $pdo->query("
                SELECT 
                    t.teacher_id, 
                    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
                FROM 
                    teachers t
                JOIN
                    accounts a ON t.account_id = a.account_id
                WHERE 
                    a.role = 'teacher'
                ORDER BY 
                    t.last_name, t.first_name
            ");
            $teachers = $stmt->fetchAll();
            
            // Обработка обновления курса
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $description = isset($_POST['description']) ? trim($_POST['description']) : '';
                $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
                $category = isset($_POST['category']) ? trim($_POST['category']) : '';
                $level = isset($_POST['level']) ? $_POST['level'] : 'beginner';
                $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
                $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
                
                // Проверка заполнения обязательных полей
                if (empty($title) || $teacher_id <= 0) {
                    $message = 'Заполните все обязательные поля';
                    $messageType = 'error';
                } else {
                    // Обновление курса
                    $stmt = $pdo->prepare("
                        UPDATE courses 
                        SET 
                            teacher_id = :teacher_id,
                            title = :title,
                            description = :description,
                            category = :category,
                            level = :level,
                            price = :price,
                            status = :status
                        WHERE 
                            course_id = :id
                    ");
                    
                    $result = $stmt->execute([
                        ':id' => $id,
                        ':teacher_id' => $teacher_id,
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
                        header('Location: courses.php?message=Курс успешно обновлен&messageType=success');
                        exit;
                    } else {
                        $message = 'Ошибка при обновлении курса';
                        $messageType = 'error';
                    }
                }
            }
            
            // Получение данных курса для формы редактирования
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = :id");
            $stmt->execute([':id' => $id]);
            $course = $stmt->fetch();
        }
        break;
        
    case 'delete':
        // Проверка существования курса
        if ($id <= 0 || !courseExists($pdo, $id)) {
            $message = 'Курс не найден';
            $messageType = 'error';
        } else {
            // Начинаем транзакцию для безопасного удаления связанных данных
            $pdo->beginTransaction();
            
            try {
                // Удаление связанных записей в lesson_progress
                $stmt = $pdo->prepare("
                    DELETE FROM lesson_progress 
                    WHERE lesson_id IN (SELECT lesson_id FROM lessons WHERE course_id = :id)
                ");
                $stmt->execute([':id' => $id]);
                
                // Удаление уроков курса
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE course_id = :id");
                $stmt->execute([':id' => $id]);
                
                // Удаление записей о записи на курс
                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE course_id = :id");
                $stmt->execute([':id' => $id]);
                
                // Удаление самого курса
                $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = :id");
                $stmt->execute([':id' => $id]);
                
                // Фиксируем транзакцию
                $pdo->commit();
                
                $message = 'Курс успешно удален';
                $messageType = 'success';
            } catch (Exception $e) {
                // Откат транзакции в случае ошибки
                $pdo->rollBack();
                
                $message = 'Ошибка при удалении курса: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // После удаления перенаправляем на список курсов
        header('Location: courses.php?message=' . urlencode($message) . '&messageType=' . $messageType);
        exit;
        break;
        
    case 'list':
    default:
        // Обработка GET-параметров для списка
        $message = isset($_GET['message']) ? $_GET['message'] : $message;
        $messageType = isset($_GET['messageType']) ? $_GET['messageType'] : $messageType;
        
        // Получение списка всех курсов
        $stmt = $pdo->query("
            SELECT 
                c.course_id,
                c.title,
                c.category,
                c.level,
                c.price,
                c.created_at,
                c.status,
                CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) AS lesson_count,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) AS enrollment_count
            FROM 
                courses c
            JOIN 
                teachers t ON c.teacher_id = t.teacher_id
            ORDER BY 
                c.course_id DESC
        ");
        $courses = $stmt->fetchAll();
        break;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление курсами | Админ-панель</title>
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
        .btn-success {
            background-color: #2ecc71;
            color: white;
        }
        .btn-success:hover {
            background-color: #29b362;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-draft {
            background-color: #f1c40f;
            color: #000;
        }
        .status-published {
            background-color: #2ecc71;
            color: white;
        }
        .status-archived {
            background-color: #95a5a6;
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
            height: 100px;
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
                <li><a href="courses.php" class="active">Управление курсами</a></li>
                <li><a href="lessons.php">Управление уроками</a></li>
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
                        echo 'Добавление нового курса';
                    } elseif ($action === 'edit') {
                        echo 'Редактирование курса';
                    } else {
                        echo 'Управление курсами';
                    }
                    ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn">Добавить курс</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- Список курсов -->
                <div class="card">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Преподаватель</th>
                            <th>Уровень</th>
                            <th>Цена</th>
                            <th>Уроки</th>
                            <th>Студенты</th>
                            <th>Статус</th>
                            <th>Дата создания</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo $course['course_id']; ?></td>
                            <td><?php echo htmlspecialchars($course['title']); ?></td>
                            <td><?php echo htmlspecialchars($course['category']); ?></td>
                            <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                            <td>
                                <?php 
                                $levelText = '';
                                switch($course['level']) {
                                    case 'beginner': $levelText = 'Начинающий'; break;
                                    case 'intermediate': $levelText = 'Средний'; break;
                                    case 'advanced': $levelText = 'Продвинутый'; break;
                                }
                                echo $levelText;
                                ?>
                            </td>
                            <td><?php echo number_format($course['price'], 2) . ' руб.'; ?></td>
                            <td><?php echo $course['lesson_count']; ?></td>
                            <td><?php echo $course['enrollment_count']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $course['status']; ?>">
                                    <?php 
                                    $statusText = '';
                                    switch($course['status']) {
                                        case 'draft': $statusText = 'Черновик'; break;
                                        case 'published': $statusText = 'Опубликован'; break;
                                        case 'archived': $statusText = 'Архивирован'; break;
                                    }
                                    echo $statusText;
                                    ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($course['created_at'])); ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $course['course_id']; ?>" class="btn">Редактировать</a>
                                <a href="lessons.php?course_id=<?php echo $course['course_id']; ?>" class="btn">Уроки</a>
                                <a href="?action=delete&id=<?php echo $course['course_id']; ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот курс?')">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Форма добавления/редактирования курса -->
                <div class="card">
                    <form method="post">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="title">Название курса *</label>
                                    <input type="text" id="title" name="title" value="<?php echo isset($course) ? htmlspecialchars($course['title']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="teacher_id">Преподаватель *</label>
                                    <select id="teacher_id" name="teacher_id" required>
                                        <option value="">Выберите преподавателя</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['teacher_id']; ?>" <?php echo (isset($course) && $course['teacher_id'] == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Описание курса</label>
                            <textarea id="description" name="description"><?php echo isset($course) ? htmlspecialchars($course['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="category">Категория</label>
                                    <input type="text" id="category" name="category" value="<?php echo isset($course) ? htmlspecialchars($course['category']) : ''; ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="level">Уровень сложности</label>
                                    <select id="level" name="level">
                                        <option value="beginner" <?php echo (isset($course) && $course['level'] === 'beginner') ? 'selected' : ''; ?>>Начинающий</option>
                                        <option value="intermediate" <?php echo (isset($course) && $course['level'] === 'intermediate') ? 'selected' : ''; ?>>Средний</option>
                                        <option value="advanced" <?php echo (isset($course) && $course['level'] === 'advanced') ? 'selected' : ''; ?>>Продвинутый</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="price">Цена (руб.)</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($course) ? $course['price'] : '0.00'; ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select id="status" name="status">
                                        <option value="draft" <?php echo (isset($course) && $course['status'] === 'draft') ? 'selected' : ''; ?>>Черновик</option>
                                        <option value="published" <?php echo (isset($course) && $course['status'] === 'published') ? 'selected' : ''; ?>>Опубликован</option>
                                        <option value="archived" <?php echo (isset($course) && $course['status'] === 'archived') ? 'selected' : ''; ?>>Архивирован</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <button type="submit" class="btn">Сохранить</button>
                            <a href="courses.php" class="btn">Отмена</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 