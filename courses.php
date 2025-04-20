<?php require_once 'config.php'; ?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список курсов | Платформа онлайн образования</title>
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
        .sort-options {
            margin: 20px 0;
            text-align: right;
        }
        .sort-options a {
            margin-left: 10px;
            padding: 5px 10px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }
        .sort-options a:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <header>
        <h1>Платформа онлайн образования</h1>
    </header>
    <nav>
        <ul>
            <li><a href="index.php">Главная</a></li>
            <li><a href="courses.php">Список курсов</a></li>
            <li><a href="filtered_courses.php">Фильтр курсов</a></li>
            <li><a href="temp_table.php">Временная таблица</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="section">
            <h2>Список доступных курсов</h2>
            
            <?php
            // Определение параметра сортировки
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'title_asc';
            
            // Определение порядка сортировки
            switch ($sort) {
                case 'title_asc':
                    $orderBy = 'c.title ASC';
                    break;
                case 'title_desc':
                    $orderBy = 'c.title DESC';
                    break;
                case 'price_asc':
                    $orderBy = 'c.price ASC';
                    break;
                case 'price_desc':
                    $orderBy = 'c.price DESC';
                    break;
                case 'date_asc':
                    $orderBy = 'c.created_at ASC';
                    break;
                case 'date_desc':
                    $orderBy = 'c.created_at DESC';
                    break;
                default:
                    $orderBy = 'c.title ASC';
            }
            
            // Выполнение многотабличного запроса
            $query = "
                SELECT 
                    c.course_id,
                    c.title AS course_title,
                    c.description,
                    c.category,
                    c.level,
                    c.price,
                    c.created_at,
                    c.status,
                    t.first_name,
                    t.last_name,
                    t.specialization,
                    t.rating,
                    (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) AS lesson_count
                FROM 
                    courses c
                JOIN 
                    teachers t ON c.teacher_id = t.teacher_id
                WHERE 
                    c.status = 'published'
                ORDER BY 
                    $orderBy
            ";
            
            $stmt = $pdo->query($query);
            $courses = $stmt->fetchAll();
            ?>
            
            <div class="sort-options">
                <strong>Сортировать по:</strong>
                <a href="?sort=title_asc">Названию (А-Я)</a>
                <a href="?sort=title_desc">Названию (Я-А)</a>
                <a href="?sort=price_asc">Цене (возр.)</a>
                <a href="?sort=price_desc">Цене (убыв.)</a>
                <a href="?sort=date_asc">Дате (стар.)</a>
                <a href="?sort=date_desc">Дате (нов.)</a>
            </div>
            
            <table>
                <tr>
                    <th>Название курса</th>
                    <th>Описание</th>
                    <th>Категория</th>
                    <th>Уровень</th>
                    <th>Количество уроков</th>
                    <th>Преподаватель</th>
                    <th>Специализация</th>
                    <th>Рейтинг</th>
                    <th>Цена</th>
                    <th>Дата создания</th>
                </tr>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                    <td><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></td>
                    <td><?php echo htmlspecialchars($course['category']); ?></td>
                    <td><?php echo htmlspecialchars($course['level']); ?></td>
                    <td><?php echo $course['lesson_count']; ?></td>
                    <td><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($course['specialization']); ?></td>
                    <td><?php echo $course['rating']; ?></td>
                    <td><?php echo number_format($course['price'], 2) . ' руб.'; ?></td>
                    <td><?php echo date('d.m.Y', strtotime($course['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html> 