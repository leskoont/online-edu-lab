<?php
session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Параметры сортировки и фильтрации
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'enrolled_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Валидация параметров сортировки
$allowedSortFields = ['title', 'enrolled_at', 'progress', 'category', 'level'];
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'enrolled_at';
}

$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Построение запроса с учетом фильтров
$query = "
    SELECT 
        c.course_id,
        c.title,
        c.description,
        c.category,
        c.level,
        c.price,
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
";

// Добавление фильтра по статусу
if (!empty($statusFilter)) {
    $query .= " AND e.status = :status";
}

// Добавление сортировки
$query .= " ORDER BY " . $sortBy . " " . $sortOrder;

$stmt = $pdo->prepare($query);
$params = [':user_id' => $userId];

if (!empty($statusFilter)) {
    $params[':status'] = $statusFilter;
}

$stmt->execute($params);
$courses = $stmt->fetchAll();

// Функция для получения ссылки сортировки
function getSortLink($field, $currentSortBy, $currentSortOrder) {
    $order = ($currentSortBy === $field && $currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
    $statusFilter = isset($_GET['status']) ? '&status=' . $_GET['status'] : '';
    return '?sort=' . $field . '&order=' . $order . $statusFilter;
}

// Функция для получения иконки сортировки
function getSortIcon($field, $currentSortBy, $currentSortOrder) {
    if ($currentSortBy !== $field) {
        return '';
    }
    return ($currentSortOrder === 'ASC') ? '↑' : '↓';
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
        table th a {
            color: #2c3e50;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        table th a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            background-color: #2c3e50;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 3px;
            transition: background-color 0.3s;
            margin-right: 5px;
            font-size: 14px;
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
        .filter-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .filter-panel select {
            padding: 8px;
            margin-left: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .course-description {
            margin-top: 5px;
            font-size: 14px;
            color: #7f8c8d;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
    </style>
</head>
<body>
    <header>
        <h1>Мои курсы</h1>
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
            <h2>Мои курсы</h2>
            
            <div class="filter-panel">
                <div>
                    <form method="GET" action="">
                        <label for="status">Фильтр по статусу:</label>
                        <select id="status" name="status" onchange="this.form.submit()">
                            <option value="" <?php if ($statusFilter === '') echo 'selected'; ?>>Все курсы</option>
                            <option value="active" <?php if ($statusFilter === 'active') echo 'selected'; ?>>Активные</option>
                            <option value="completed" <?php if ($statusFilter === 'completed') echo 'selected'; ?>>Завершенные</option>
                            <option value="dropped" <?php if ($statusFilter === 'dropped') echo 'selected'; ?>>Отмененные</option>
                        </select>
                        <input type="hidden" name="sort" value="<?php echo $sortBy; ?>">
                        <input type="hidden" name="order" value="<?php echo $sortOrder; ?>">
                    </form>
                </div>
                <div>
                    <a href="../courses.php" class="btn">Записаться на новый курс</a>
                </div>
            </div>
            
            <?php if (count($courses) > 0): ?>
                <table>
                    <tr>
                        <th><a href="<?php echo getSortLink('title', $sortBy, $sortOrder); ?>">Название курса <?php echo getSortIcon('title', $sortBy, $sortOrder); ?></a></th>
                        <th>Преподаватель</th>
                        <th><a href="<?php echo getSortLink('category', $sortBy, $sortOrder); ?>">Категория <?php echo getSortIcon('category', $sortBy, $sortOrder); ?></a></th>
                        <th><a href="<?php echo getSortLink('level', $sortBy, $sortOrder); ?>">Уровень <?php echo getSortIcon('level', $sortBy, $sortOrder); ?></a></th>
                        <th><a href="<?php echo getSortLink('progress', $sortBy, $sortOrder); ?>">Прогресс <?php echo getSortIcon('progress', $sortBy, $sortOrder); ?></a></th>
                        <th>Статус</th>
                        <th><a href="<?php echo getSortLink('enrolled_at', $sortBy, $sortOrder); ?>">Дата записи <?php echo getSortIcon('enrolled_at', $sortBy, $sortOrder); ?></a></th>
                        <th>Действия</th>
                    </tr>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                            <div class="course-description"><?php echo htmlspecialchars($course['description']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['category']); ?></td>
                        <td>
                            <?php 
                                switch ($course['level']) {
                                    case 'beginner': echo 'Начальный'; break;
                                    case 'intermediate': echo 'Средний'; break;
                                    case 'advanced': echo 'Продвинутый'; break;
                                    default: echo $course['level'];
                                }
                            ?>
                        </td>
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
                        <td><?php echo date('d.m.Y', strtotime($course['enrolled_at'])); ?></td>
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
    </div>
</body>
</html> 