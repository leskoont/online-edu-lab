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

// Функция для проверки наличия преподавателя по ID
function teacherExists($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE teacher_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// Функция для проверки наличия аккаунта по ID
function accountExists($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE account_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// Обработка сообщений
$message = '';
$messageType = '';

// Обработка различных действий
switch ($action) {
    case 'add':
        // Получение списка аккаунтов с ролью teacher для формы
        $stmt = $pdo->prepare("
            SELECT 
                a.account_id, 
                a.username,
                a.email
            FROM 
                accounts a
            WHERE 
                a.role = 'teacher' AND
                a.account_id NOT IN (SELECT account_id FROM teachers)
            ORDER BY 
                a.username
        ");
        $stmt->execute();
        $teachers_accounts = $stmt->fetchAll();
        
        // Обработка добавления нового преподавателя
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
            $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
            $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
            $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
            $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
            
            // Проверка заполнения обязательных полей
            if (empty($first_name) || empty($last_name) || $account_id <= 0) {
                $message = 'Заполните все обязательные поля';
                $messageType = 'error';
            } else {
                // Проверка существования аккаунта и отсутствия связи с другим преподавателем
                if (!accountExists($pdo, $account_id)) {
                    $message = 'Выбранный аккаунт не существует';
                    $messageType = 'error';
                } else {
                    // Проверка, что этот аккаунт еще не привязан к другому преподавателю
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE account_id = :account_id");
                    $stmt->execute([':account_id' => $account_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Этот аккаунт уже привязан к другому преподавателю';
                        $messageType = 'error';
                    } else {
                        // Добавление нового преподавателя
                        $stmt = $pdo->prepare("
                            INSERT INTO teachers (account_id, first_name, last_name, bio, specialization) 
                            VALUES (:account_id, :first_name, :last_name, :bio, :specialization)
                        ");
                        
                        $result = $stmt->execute([
                            ':account_id' => $account_id,
                            ':first_name' => $first_name,
                            ':last_name' => $last_name,
                            ':bio' => $bio,
                            ':specialization' => $specialization
                        ]);
                        
                        if ($result) {
                            $message = 'Преподаватель успешно создан';
                            $messageType = 'success';
                            header('Location: teachers.php?message=Преподаватель успешно создан&messageType=success');
                            exit;
                        } else {
                            $message = 'Ошибка при создании преподавателя';
                            $messageType = 'error';
                        }
                    }
                }
            }
        }
        break;
        
    case 'edit':
        // Проверка существования преподавателя
        if ($id <= 0 || !teacherExists($pdo, $id)) {
            $message = 'Преподаватель не найден';
            $messageType = 'error';
            $action = 'list'; // Возвращаемся к списку
        } else {
            // Получение данных преподавателя для формы редактирования
            $stmt = $pdo->prepare("
                SELECT 
                    t.*, 
                    a.username, 
                    a.email 
                FROM 
                    teachers t
                JOIN 
                    accounts a ON t.account_id = a.account_id
                WHERE 
                    t.teacher_id = :id
            ");
            $stmt->execute([':id' => $id]);
            $teacher = $stmt->fetch();
            
            // Обработка обновления преподавателя
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
                $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
                $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
                $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
                
                // Проверка заполнения обязательных полей
                if (empty($first_name) || empty($last_name)) {
                    $message = 'Заполните все обязательные поля';
                    $messageType = 'error';
                } else {
                    // Обновление данных преподавателя
                    $stmt = $pdo->prepare("
                        UPDATE teachers 
                        SET 
                            first_name = :first_name,
                            last_name = :last_name,
                            bio = :bio,
                            specialization = :specialization
                        WHERE 
                            teacher_id = :id
                    ");
                    
                    $result = $stmt->execute([
                        ':id' => $id,
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':bio' => $bio,
                        ':specialization' => $specialization
                    ]);
                    
                    if ($result) {
                        $message = 'Преподаватель успешно обновлен';
                        $messageType = 'success';
                        header('Location: teachers.php?message=Преподаватель успешно обновлен&messageType=success');
                        exit;
                    } else {
                        $message = 'Ошибка при обновлении преподавателя';
                        $messageType = 'error';
                    }
                }
            }
        }
        break;
        
    case 'delete':
        // Проверка существования преподавателя
        if ($id <= 0 || !teacherExists($pdo, $id)) {
            $message = 'Преподаватель не найден';
            $messageType = 'error';
        } else {
            // Проверка, есть ли курсы, связанные с этим преподавателем
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'Невозможно удалить преподавателя, так как с ним связаны курсы';
                $messageType = 'error';
            } else {
                // Удаление преподавателя
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE teacher_id = :id");
                $result = $stmt->execute([':id' => $id]);
                
                if ($result) {
                    $message = 'Преподаватель успешно удален';
                    $messageType = 'success';
                } else {
                    $message = 'Ошибка при удалении преподавателя';
                    $messageType = 'error';
                }
            }
            
            // Перенаправление на список преподавателей после удаления
            header('Location: teachers.php?message=' . urlencode($message) . '&messageType=' . $messageType);
            exit;
        }
        break;
        
    case 'list':
    default:
        // Обработка GET-параметров для списка
        $message = isset($_GET['message']) ? $_GET['message'] : $message;
        $messageType = isset($_GET['messageType']) ? $_GET['messageType'] : $messageType;
        
        // Получение списка преподавателей
        $stmt = $pdo->query("
            SELECT 
                t.teacher_id, 
                t.first_name,
                t.last_name,
                t.specialization,
                t.rating,
                a.username,
                a.email,
                (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = t.teacher_id) AS course_count
            FROM 
                teachers t
            JOIN 
                accounts a ON t.account_id = a.account_id
            ORDER BY 
                t.last_name, t.first_name
        ");
        $teachers = $stmt->fetchAll();
        break;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление преподавателями | Админ-панель</title>
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
        .rating {
            color: #f39c12;
            font-weight: bold;
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
                <li><a href="teachers.php" class="active">Управление преподавателями</a></li>
                <li><a href="courses.php">Управление курсами</a></li>
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
                        echo 'Добавление нового преподавателя';
                    } elseif ($action === 'edit') {
                        echo 'Редактирование преподавателя';
                    } else {
                        echo 'Управление преподавателями';
                    }
                    ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                <div>
                    <a href="?action=add" class="btn">Добавить преподавателя</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- Список преподавателей -->
                <div class="card">
                    <?php if (count($teachers) > 0): ?>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Фамилия</th>
                            <th>Специализация</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Рейтинг</th>
                            <th>Курсы</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo $teacher['teacher_id']; ?></td>
                            <td><?php echo htmlspecialchars($teacher['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['specialization']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td class="rating">
                                <?php echo number_format($teacher['rating'], 1); ?> / 5.0
                            </td>
                            <td><?php echo $teacher['course_count']; ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $teacher['teacher_id']; ?>" class="btn">Редактировать</a>
                                <a href="?action=delete&id=<?php echo $teacher['teacher_id']; ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этого преподавателя?')">Удалить</a>
                                <a href="courses.php?teacher_id=<?php echo $teacher['teacher_id']; ?>" class="btn">Курсы</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php else: ?>
                    <p>Преподаватели не найдены.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($action === 'add'): ?>
                <!-- Форма добавления преподавателя -->
                <div class="card">
                    <form method="post">
                        <div class="form-group">
                            <label for="account_id">Аккаунт преподавателя *</label>
                            <select id="account_id" name="account_id" required>
                                <option value="">Выберите аккаунт</option>
                                <?php foreach ($teachers_accounts as $account): ?>
                                <option value="<?php echo $account['account_id']; ?>">
                                    <?php echo htmlspecialchars($account['username'] . ' (' . $account['email'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($teachers_accounts) === 0): ?>
                            <p class="message error">Нет доступных аккаунтов с ролью преподавателя. <a href="accounts.php?action=add">Создайте новый аккаунт</a> с ролью преподавателя.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="first_name">Имя *</label>
                                    <input type="text" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="last_name">Фамилия *</label>
                                    <input type="text" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Специализация</label>
                            <input type="text" id="specialization" name="specialization">
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">Биография</label>
                            <textarea id="bio" name="bio"></textarea>
                        </div>
                        
                        <div class="actions">
                            <button type="submit" class="btn" <?php echo (count($teachers_accounts) === 0) ? 'disabled' : ''; ?>>Сохранить</button>
                            <a href="teachers.php" class="btn">Отмена</a>
                        </div>
                    </form>
                </div>
            <?php elseif ($action === 'edit'): ?>
                <!-- Форма редактирования преподавателя -->
                <div class="card">
                    <form method="post">
                        <div class="form-group">
                            <label>Информация об аккаунте</label>
                            <p>
                                <strong>Логин:</strong> <?php echo htmlspecialchars($teacher['username']); ?><br>
                                <strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?>
                            </p>
                            <p><a href="accounts.php?action=edit&id=<?php echo $teacher['account_id']; ?>" class="btn">Редактировать аккаунт</a></p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="first_name">Имя *</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($teacher['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="last_name">Фамилия *</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($teacher['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Специализация</label>
                            <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($teacher['specialization']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">Биография</label>
                            <textarea id="bio" name="bio"><?php echo htmlspecialchars($teacher['bio']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Текущий рейтинг: <span class="rating"><?php echo number_format($teacher['rating'], 1); ?> / 5.0</span></label>
                            <p>Рейтинг рассчитывается автоматически на основе оценок студентов.</p>
                        </div>
                        
                        <div class="actions">
                            <button type="submit" class="btn">Сохранить</button>
                            <a href="teachers.php" class="btn">Отмена</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 