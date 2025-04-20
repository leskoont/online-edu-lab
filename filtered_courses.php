<?php require_once 'config.php'; ?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Фильтр курсов | Платформа онлайн образования</title>
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
        .filter-form {
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-form label {
            display: inline-block;
            margin: 0 15px 10px 0;
        }
        .filter-form select, .filter-form input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter-form input[type="submit"] {
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-form input[type="submit"]:hover {
            background-color: #2980b9;
        }
        .checkbox-group {
            display: inline-block;
            margin-right: 15px;
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
            <h2>Фильтр курсов</h2>
            
            <?php
            // Получение категорий курсов для выпадающего списка
            $stmt = $pdo->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL ORDER BY category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Получение списка преподавателей
            $stmt = $pdo->query("SELECT teacher_id, first_name, last_name FROM teachers ORDER BY last_name, first_name");
            $teachers = $stmt->fetchAll();
            
            // Обработка запроса фильтрации
            $whereConditions = [];
            $params = [];
            
            // Установка значений фильтров по умолчанию
            $selectedCategory = '';
            $selectedLevel = '';
            $selectedTeachers = [];
            $minPrice = '';
            $maxPrice = '';
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Фильтр по категории
                if (!empty($_POST['category'])) {
                    $selectedCategory = $_POST['category'];
                    $whereConditions[] = "c.category = :category";
                    $params[':category'] = $selectedCategory;
                }
                
                // Фильтр по уровню
                if (!empty($_POST['level'])) {
                    $selectedLevel = $_POST['level'];
                    $whereConditions[] = "c.level = :level";
                    $params[':level'] = $selectedLevel;
                }
                
                // Фильтр по преподавателям
                if (isset($_POST['teachers']) && is_array($_POST['teachers']) && count($_POST['teachers']) > 0) {
                    $selectedTeachers = $_POST['teachers'];
                    $teacherPlaceholders = [];
                    
                    foreach ($_POST['teachers'] as $index => $teacherId) {
                        $paramName = ':teacher' . $index;
                        $teacherPlaceholders[] = $paramName;
                        $params[$paramName] = $teacherId;
                    }
                    
                    $whereConditions[] = "t.teacher_id IN (" . implode(", ", $teacherPlaceholders) . ")";
                }
                
                // Фильтр по диапазону цен
                if (!empty($_POST['min_price'])) {
                    $minPrice = $_POST['min_price'];
                    $whereConditions[] = "c.price >= :min_price";
                    $params[':min_price'] = $minPrice;
                }
                
                if (!empty($_POST['max_price'])) {
                    $maxPrice = $_POST['max_price'];
                    $whereConditions[] = "c.price <= :max_price";
                    $params[':max_price'] = $maxPrice;
                }
            }
            
            // Базовое условие для статуса курса
            $whereConditions[] = "c.status = 'published'";
            
            // Составление строки WHERE
            $whereClause = count($whereConditions) > 0 ? " WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Выполнение многотабличного запроса с фильтрацией
            $query = "
                SELECT 
                    c.course_id,
                    c.title AS course_title,
                    c.description,
                    c.category,
                    c.level,
                    c.price,
                    c.created_at,
                    t.first_name,
                    t.last_name,
                    t.specialization,
                    t.rating,
                    (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) AS lesson_count
                FROM 
                    courses c
                JOIN 
                    teachers t ON c.teacher_id = t.teacher_id
                $whereClause
                ORDER BY 
                    c.title ASC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $filteredCourses = $stmt->fetchAll();
            ?>
            
            <form method="post" class="filter-form">
                <div>
                    <label for="category">Категория:</label>
                    <select name="category" id="category">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="level">Уровень:</label>
                    <select name="level" id="level">
                        <option value="">Все уровни</option>
                        <option value="beginner" <?php echo $selectedLevel === 'beginner' ? 'selected' : ''; ?>>Начинающий</option>
                        <option value="intermediate" <?php echo $selectedLevel === 'intermediate' ? 'selected' : ''; ?>>Средний</option>
                        <option value="advanced" <?php echo $selectedLevel === 'advanced' ? 'selected' : ''; ?>>Продвинутый</option>
                    </select>
                </div>
                
                <div style="margin-top: 10px;">
                    <label>Преподаватели:</label>
                    <?php foreach ($teachers as $index => $teacher): ?>
                    <div class="checkbox-group">
                        <input type="checkbox" name="teachers[]" id="teacher<?php echo $teacher['teacher_id']; ?>" 
                               value="<?php echo $teacher['teacher_id']; ?>" 
                               <?php echo in_array($teacher['teacher_id'], $selectedTeachers) ? 'checked' : ''; ?>>
                        <label for="teacher<?php echo $teacher['teacher_id']; ?>">
                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                        </label>
                    </div>
                    <?php if (($index + 1) % 3 === 0): ?>
                    <br>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 10px;">
                    <label for="min_price">Цена от:</label>
                    <input type="text" name="min_price" id="min_price" value="<?php echo htmlspecialchars($minPrice); ?>" placeholder="Мин. цена">
                    
                    <label for="max_price">до:</label>
                    <input type="text" name="max_price" id="max_price" value="<?php echo htmlspecialchars($maxPrice); ?>" placeholder="Макс. цена">
                </div>
                
                <div style="margin-top: 15px;">
                    <input type="submit" value="Применить фильтры">
                </div>
            </form>
            
            <h3>Результаты поиска (найдено: <?php echo count($filteredCourses); ?>)</h3>
            
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
                <?php if (count($filteredCourses) > 0): ?>
                    <?php foreach ($filteredCourses as $course): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                        <td><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></td>
                        <td><?php echo htmlspecialchars($course['category']); ?></td>
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
                        <td><?php echo $course['lesson_count']; ?></td>
                        <td><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['specialization']); ?></td>
                        <td><?php echo $course['rating']; ?></td>
                        <td><?php echo number_format($course['price'], 2) . ' руб.'; ?></td>
                        <td><?php echo date('d.m.Y', strtotime($course['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center;">По заданным критериям курсы не найдены</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>
</html> 