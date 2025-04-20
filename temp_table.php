<?php require_once 'config.php'; ?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Временная таблица | Платформа онлайн образования</title>
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
        h1, h2, h3 {
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
        form {
            margin-top: 20px;
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #2980b9;
        }
        .action-links {
            margin-top: 10px;
        }
        .action-links a {
            margin-right: 10px;
            color: #3498db;
            text-decoration: none;
        }
        .action-links a:hover {
            text-decoration: underline;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
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
            <h2>Работа с временной таблицей (MEMORY)</h2>
            
            <?php
            // Создание временной таблицы, если она не существует
            $createTempTable = "
                CREATE TABLE IF NOT EXISTS temp_announcements (
                    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(100) NOT NULL,
                    message TEXT NOT NULL,
                    target_audience ENUM('all', 'students', 'teachers') NOT NULL DEFAULT 'all',
                    importance ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=MEMORY
            ";
            
            try {
                $pdo->exec($createTempTable);
                
                // Проверка на действия пользователя
                $message = '';
                $messageType = '';
                
                // Обработка добавления объявления
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
                    if (!empty($_POST['title']) && !empty($_POST['message']) && !empty($_POST['target_audience']) && !empty($_POST['importance'])) {
                        $stmt = $pdo->prepare("INSERT INTO temp_announcements (title, message, target_audience, importance) VALUES (:title, :message, :target_audience, :importance)");
                        $stmt->execute([
                            ':title' => $_POST['title'],
                            ':message' => $_POST['message'],
                            ':target_audience' => $_POST['target_audience'],
                            ':importance' => $_POST['importance']
                        ]);
                        
                        $message = 'Объявление успешно добавлено!';
                        $messageType = 'success';
                    } else {
                        $message = 'Заполните все поля формы!';
                        $messageType = 'error';
                    }
                }
                
                // Обработка обновления объявления
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
                    if (!empty($_POST['announcement_id']) && !empty($_POST['title']) && !empty($_POST['message']) && !empty($_POST['target_audience']) && !empty($_POST['importance'])) {
                        $stmt = $pdo->prepare("UPDATE temp_announcements SET title = :title, message = :message, target_audience = :target_audience, importance = :importance WHERE announcement_id = :id");
                        $stmt->execute([
                            ':id' => $_POST['announcement_id'],
                            ':title' => $_POST['title'],
                            ':message' => $_POST['message'],
                            ':target_audience' => $_POST['target_audience'],
                            ':importance' => $_POST['importance']
                        ]);
                        
                        $message = 'Объявление успешно обновлено!';
                        $messageType = 'success';
                    } else {
                        $message = 'Заполните все поля формы!';
                        $messageType = 'error';
                    }
                }
                
                // Обработка удаления объявления
                if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                    $stmt = $pdo->prepare("DELETE FROM temp_announcements WHERE announcement_id = :id");
                    $stmt->execute([':id' => $_GET['id']]);
                    
                    $message = 'Объявление успешно удалено!';
                    $messageType = 'success';
                }
                
                // Получение данных для редактирования, если передан ID
                $editAnnouncement = null;
                if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM temp_announcements WHERE announcement_id = :id");
                    $stmt->execute([':id' => $_GET['id']]);
                    $editAnnouncement = $stmt->fetch();
                }
                
                // Получение всех объявлений из временной таблицы
                $stmt = $pdo->query("SELECT * FROM temp_announcements ORDER BY created_at DESC");
                $announcements = $stmt->fetchAll();
                
                // Отображение сообщения, если оно есть
                if (!empty($message)) {
                    echo '<div class="message ' . $messageType . '">' . htmlspecialchars($message) . '</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="message error">Ошибка базы данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
            
            <h3><?php echo $editAnnouncement ? 'Редактировать объявление' : 'Добавить новое объявление'; ?></h3>
            
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editAnnouncement ? 'update' : 'add'; ?>">
                <?php if ($editAnnouncement): ?>
                <input type="hidden" name="announcement_id" value="<?php echo $editAnnouncement['announcement_id']; ?>">
                <?php endif; ?>
                
                <label for="title">Заголовок:</label>
                <input type="text" id="title" name="title" value="<?php echo $editAnnouncement ? htmlspecialchars($editAnnouncement['title']) : ''; ?>" required>
                
                <label for="message">Сообщение:</label>
                <textarea id="message" name="message" rows="4" required><?php echo $editAnnouncement ? htmlspecialchars($editAnnouncement['message']) : ''; ?></textarea>
                
                <label for="target_audience">Целевая аудитория:</label>
                <select id="target_audience" name="target_audience" required>
                    <option value="all" <?php echo ($editAnnouncement && $editAnnouncement['target_audience'] === 'all') ? 'selected' : ''; ?>>Все пользователи</option>
                    <option value="students" <?php echo ($editAnnouncement && $editAnnouncement['target_audience'] === 'students') ? 'selected' : ''; ?>>Студенты</option>
                    <option value="teachers" <?php echo ($editAnnouncement && $editAnnouncement['target_audience'] === 'teachers') ? 'selected' : ''; ?>>Преподаватели</option>
                </select>
                
                <label for="importance">Важность:</label>
                <select id="importance" name="importance" required>
                    <option value="low" <?php echo ($editAnnouncement && $editAnnouncement['importance'] === 'low') ? 'selected' : ''; ?>>Низкая</option>
                    <option value="medium" <?php echo ($editAnnouncement && $editAnnouncement['importance'] === 'medium') ? 'selected' : ''; ?>>Средняя</option>
                    <option value="high" <?php echo ($editAnnouncement && $editAnnouncement['importance'] === 'high') ? 'selected' : ''; ?>>Высокая</option>
                </select>
                
                <input type="submit" value="<?php echo $editAnnouncement ? 'Обновить объявление' : 'Добавить объявление'; ?>">
                
                <?php if ($editAnnouncement): ?>
                <a href="temp_table.php" style="margin-left: 10px; color: #e74c3c;">Отменить редактирование</a>
                <?php endif; ?>
            </form>
            
            <h3>Список объявлений</h3>
            
            <?php if (count($announcements) > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Заголовок</th>
                    <th>Сообщение</th>
                    <th>Целевая аудитория</th>
                    <th>Важность</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
                <?php foreach ($announcements as $announcement): ?>
                <tr>
                    <td><?php echo $announcement['announcement_id']; ?></td>
                    <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                    <td><?php echo htmlspecialchars(substr($announcement['message'], 0, 100)) . (strlen($announcement['message']) > 100 ? '...' : ''); ?></td>
                    <td>
                        <?php
                        switch ($announcement['target_audience']) {
                            case 'all': echo 'Все пользователи'; break;
                            case 'students': echo 'Студенты'; break;
                            case 'teachers': echo 'Преподаватели'; break;
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        switch ($announcement['importance']) {
                            case 'low': echo 'Низкая'; break;
                            case 'medium': echo 'Средняя'; break;
                            case 'high': echo 'Высокая'; break;
                        }
                        ?>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($announcement['created_at'])); ?></td>
                    <td class="action-links">
                        <a href="?action=edit&id=<?php echo $announcement['announcement_id']; ?>">Редактировать</a>
                        <a href="?action=delete&id=<?php echo $announcement['announcement_id']; ?>" onclick="return confirm('Вы уверены, что хотите удалить это объявление?')">Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <p>Объявления отсутствуют. Добавьте новое объявление, используя форму выше.</p>
            <?php endif; ?>
            
            <h3>Информация о таблице</h3>
            <p>
                Временная таблица <code>temp_announcements</code> использует тип хранения <strong>MEMORY</strong>, 
                который хранит все данные в оперативной памяти, что обеспечивает очень быстрый доступ для операций чтения и записи.
                При перезапуске сервера MySQL данные в таблице будут потеряны, поскольку они не сохраняются на диск.
            </p>
            
            <?php
            // Получение информации о таблице
            $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'temp_announcements'");
            $tableInfo = $stmt->fetch();
            ?>
            
            <table>
                <tr>
                    <th>Название таблицы</th>
                    <th>Тип хранения</th>
                    <th>Количество строк</th>
                    <th>Размер данных</th>
                    <th>Кодировка</th>
                </tr>
                <tr>
                    <td><?php echo $tableInfo['Name']; ?></td>
                    <td><?php echo $tableInfo['Engine']; ?></td>
                    <td><?php echo $tableInfo['Rows']; ?></td>
                    <td><?php echo $tableInfo['Data_length'] . ' байт'; ?></td>
                    <td><?php echo $tableInfo['Collation']; ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html> 