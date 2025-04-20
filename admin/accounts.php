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
        // Обработка добавления нового аккаунта
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $role = isset($_POST['role']) ? $_POST['role'] : 'student';
            
            // Проверка заполнения полей
            if (empty($username) || empty($email) || empty($password)) {
                $message = 'Заполните все обязательные поля';
                $messageType = 'error';
            } else {
                // Проверка на уникальность имени пользователя и email
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE username = :username OR email = :email");
                $stmt->execute([':username' => $username, ':email' => $email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $message = 'Пользователь с таким именем или email уже существует';
                    $messageType = 'error';
                } else {
                    // Добавление нового аккаунта
                    // В реальном проекте используйте password_hash для хеширования пароля
                    $stmt = $pdo->prepare("
                        INSERT INTO accounts (username, email, password, role, created_at) 
                        VALUES (:username, :email, :password, :role, NOW())
                    ");
                    
                    $result = $stmt->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $password,
                        ':role' => $role
                    ]);
                    
                    if ($result) {
                        $message = 'Аккаунт успешно создан';
                        $messageType = 'success';
                        
                        // Если создан преподаватель, создаем запись в таблице teachers
                        if ($role === 'teacher') {
                            $accountId = $pdo->lastInsertId();
                            $stmt = $pdo->prepare("
                                INSERT INTO teachers (account_id, first_name, last_name, specialization) 
                                VALUES (:account_id, :first_name, :last_name, :specialization)
                            ");
                            
                            $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
                            $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
                            $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
                            
                            $stmt->execute([
                                ':account_id' => $accountId,
                                ':first_name' => $firstName,
                                ':last_name' => $lastName,
                                ':specialization' => $specialization
                            ]);
                        }
                        
                        // Перенаправление на список аккаунтов
                        header('Location: accounts.php?message=Аккаунт успешно создан&messageType=success');
                        exit;
                    } else {
                        $message = 'Ошибка при создании аккаунта';
                        $messageType = 'error';
                    }
                }
            }
        }
        break;
        
    case 'edit':
        // Проверка существования аккаунта
        if ($id <= 0 || !accountExists($pdo, $id)) {
            $message = 'Аккаунт не найден';
            $messageType = 'error';
            $action = 'list'; // Возвращаемся к списку
        } else {
            // Обработка обновления аккаунта
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $role = isset($_POST['role']) ? $_POST['role'] : 'student';
                
                // Проверка заполнения полей
                if (empty($username) || empty($email)) {
                    $message = 'Заполните все обязательные поля';
                    $messageType = 'error';
                } else {
                    // Проверка на уникальность имени пользователя и email, исключая текущий ID
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM accounts 
                        WHERE (username = :username OR email = :email) AND account_id != :id
                    ");
                    $stmt->execute([':username' => $username, ':email' => $email, ':id' => $id]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Пользователь с таким именем или email уже существует';
                        $messageType = 'error';
                    } else {
                        // Получение текущей роли пользователя
                        $stmt = $pdo->prepare("SELECT role FROM accounts WHERE account_id = :id");
                        $stmt->execute([':id' => $id]);
                        $currentRole = $stmt->fetchColumn();
                        
                        // Обновление аккаунта
                        $updateSql = "
                            UPDATE accounts 
                            SET username = :username, email = :email, role = :role
                        ";
                        
                        // Если передан новый пароль, обновляем его
                        $params = [
                            ':id' => $id,
                            ':username' => $username,
                            ':email' => $email,
                            ':role' => $role
                        ];
                        
                        if (!empty($_POST['password'])) {
                            $updateSql .= ", password = :password";
                            $params[':password'] = $_POST['password']; // В реальном проекте используйте password_hash
                        }
                        
                        $updateSql .= " WHERE account_id = :id";
                        
                        $stmt = $pdo->prepare($updateSql);
                        $result = $stmt->execute($params);
                        
                        if ($result) {
                            // Обработка специфических данных в зависимости от роли
                            if ($role === 'teacher') {
                                // Проверяем, есть ли запись учителя для этого аккаунта
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE account_id = :account_id");
                                $stmt->execute([':account_id' => $id]);
                                
                                $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
                                $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
                                $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
                                
                                if ($stmt->fetchColumn() > 0) {
                                    // Обновление данных учителя
                                    $stmt = $pdo->prepare("
                                        UPDATE teachers 
                                        SET first_name = :first_name, last_name = :last_name, specialization = :specialization
                                        WHERE account_id = :account_id
                                    ");
                                } else {
                                    // Создание записи учителя
                                    $stmt = $pdo->prepare("
                                        INSERT INTO teachers (account_id, first_name, last_name, specialization) 
                                        VALUES (:account_id, :first_name, :last_name, :specialization)
                                    ");
                                }
                                
                                $stmt->execute([
                                    ':account_id' => $id,
                                    ':first_name' => $firstName,
                                    ':last_name' => $lastName,
                                    ':specialization' => $specialization
                                ]);
                            }
                            
                            $message = 'Аккаунт успешно обновлен';
                            $messageType = 'success';
                            
                            // Перенаправление на список аккаунтов
                            header('Location: accounts.php?message=Аккаунт успешно обновлен&messageType=success');
                            exit;
                        } else {
                            $message = 'Ошибка при обновлении аккаунта';
                            $messageType = 'error';
                        }
                    }
                }
            }
            
            // Получение данных аккаунта для формы редактирования
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE account_id = :id");
            $stmt->execute([':id' => $id]);
            $account = $stmt->fetch();
            
            // Если аккаунт имеет роль "teacher", получаем данные учителя
            if ($account && $account['role'] === 'teacher') {
                $stmt = $pdo->prepare("SELECT * FROM teachers WHERE account_id = :account_id");
                $stmt->execute([':account_id' => $id]);
                $teacher = $stmt->fetch();
            }
        }
        break;
        
    case 'delete':
        // Проверка существования аккаунта
        if ($id <= 0 || !accountExists($pdo, $id)) {
            $message = 'Аккаунт не найден';
            $messageType = 'error';
        } else {
            // Получение роли пользователя
            $stmt = $pdo->prepare("SELECT role FROM accounts WHERE account_id = :id");
            $stmt->execute([':id' => $id]);
            $role = $stmt->fetchColumn();
            
            // Начинаем транзакцию для безопасного удаления связанных данных
            $pdo->beginTransaction();
            
            try {
                // Если пользователь учитель, удаляем связанные записи
                if ($role === 'teacher') {
                    // Удаление записи учителя
                    $stmt = $pdo->prepare("DELETE FROM teachers WHERE account_id = :id");
                    $stmt->execute([':id' => $id]);
                    
                    // Получаем ID учителя
                    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE account_id = :id");
                    $stmt->execute([':id' => $id]);
                    $teacherId = $stmt->fetchColumn();
                    
                    if ($teacherId) {
                        // Удаление связанных курсов и их уроков
                        $stmt = $pdo->prepare("
                            DELETE FROM lessons 
                            WHERE course_id IN (SELECT course_id FROM courses WHERE teacher_id = :teacher_id)
                        ");
                        $stmt->execute([':teacher_id' => $teacherId]);
                        
                        // Удаление курсов
                        $stmt = $pdo->prepare("DELETE FROM courses WHERE teacher_id = :teacher_id");
                        $stmt->execute([':teacher_id' => $teacherId]);
                    }
                }
                
                // Удаление аккаунта
                $stmt = $pdo->prepare("DELETE FROM accounts WHERE account_id = :id");
                $stmt->execute([':id' => $id]);
                
                // Фиксируем транзакцию
                $pdo->commit();
                
                $message = 'Аккаунт успешно удален';
                $messageType = 'success';
            } catch (Exception $e) {
                // Откат транзакции в случае ошибки
                $pdo->rollBack();
                
                $message = 'Ошибка при удалении аккаунта: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // После удаления перенаправляем на список аккаунтов
        header('Location: accounts.php?message=' . urlencode($message) . '&messageType=' . $messageType);
        exit;
        break;
        
    case 'list':
    default:
        // Обработка GET-параметров для списка
        $message = isset($_GET['message']) ? $_GET['message'] : $message;
        $messageType = isset($_GET['messageType']) ? $_GET['messageType'] : $messageType;
        
        // Получение списка всех аккаунтов
        $stmt = $pdo->query("
            SELECT
                a.account_id,
                a.username,
                a.email,
                a.role,
                a.created_at,
                a.last_login,
                CASE 
                    WHEN a.role = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name) 
                    ELSE '' 
                END AS full_name
            FROM 
                accounts a
            LEFT JOIN 
                teachers t ON a.account_id = t.account_id
            ORDER BY 
                a.account_id
        ");
        $accounts = $stmt->fetchAll();
        break;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление аккаунтами | Админ-панель</title>
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-row {
            display: flex;
            margin: 0 -10px;
        }
        .form-col {
            flex: 1;
            padding: 0 10px;
        }
        .teacher-fields {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            border: 1px solid #e0e0e0;
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
                <li><a href="accounts.php" class="active">Управление аккаунтами</a></li>
                <li><a href="teachers.php">Управление преподавателями</a></li>
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
                        echo 'Добавление нового аккаунта';
                    } elseif ($action === 'edit') {
                        echo 'Редактирование аккаунта';
                    } else {
                        echo 'Управление аккаунтами';
                    }
                    ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn">Добавить аккаунт</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- Список аккаунтов -->
                <div class="card">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Имя пользователя</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Имя (для преподавателей)</th>
                            <th>Дата регистрации</th>
                            <th>Последний вход</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?php echo $account['account_id']; ?></td>
                            <td><?php echo htmlspecialchars($account['username']); ?></td>
                            <td><?php echo htmlspecialchars($account['email']); ?></td>
                            <td>
                                <?php 
                                $roleText = '';
                                switch($account['role']) {
                                    case 'student': $roleText = 'Студент'; break;
                                    case 'teacher': $roleText = 'Преподаватель'; break;
                                    case 'admin': $roleText = 'Администратор'; break;
                                }
                                echo $roleText;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($account['full_name']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($account['created_at'])); ?></td>
                            <td><?php echo $account['last_login'] ? date('d.m.Y H:i', strtotime($account['last_login'])) : 'Никогда'; ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $account['account_id']; ?>" class="btn">Редактировать</a>
                                <a href="?action=delete&id=<?php echo $account['account_id']; ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот аккаунт?')">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Форма добавления/редактирования аккаунта -->
                <div class="card">
                    <form method="post">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="username">Имя пользователя *</label>
                                    <input type="text" id="username" name="username" value="<?php echo isset($account) ? htmlspecialchars($account['username']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" value="<?php echo isset($account) ? htmlspecialchars($account['email']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="password"><?php echo $action === 'add' ? 'Пароль *' : 'Новый пароль (оставьте пустым, чтобы не менять)'; ?></label>
                                    <input type="password" id="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="role">Роль *</label>
                                    <select id="role" name="role" required onchange="toggleTeacherFields()">
                                        <option value="student" <?php echo (isset($account) && $account['role'] === 'student') ? 'selected' : ''; ?>>Студент</option>
                                        <option value="teacher" <?php echo (isset($account) && $account['role'] === 'teacher') ? 'selected' : ''; ?>>Преподаватель</option>
                                        <option value="admin" <?php echo (isset($account) && $account['role'] === 'admin') ? 'selected' : ''; ?>>Администратор</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div id="teacher-fields" class="teacher-fields" style="display: <?php echo (isset($account) && $account['role'] === 'teacher') ? 'block' : 'none'; ?>;">
                            <h3>Информация о преподавателе</h3>
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="first_name">Имя</label>
                                        <input type="text" id="first_name" name="first_name" value="<?php echo isset($teacher) ? htmlspecialchars($teacher['first_name']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="last_name">Фамилия</label>
                                        <input type="text" id="last_name" name="last_name" value="<?php echo isset($teacher) ? htmlspecialchars($teacher['last_name']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="specialization">Специализация</label>
                                <input type="text" id="specialization" name="specialization" value="<?php echo isset($teacher) ? htmlspecialchars($teacher['specialization']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="actions">
                            <button type="submit" class="btn">Сохранить</button>
                            <a href="accounts.php" class="btn">Отмена</a>
                        </div>
                    </form>
                </div>
                
                <script>
                    function toggleTeacherFields() {
                        var role = document.getElementById('role').value;
                        var teacherFields = document.getElementById('teacher-fields');
                        
                        if (role === 'teacher') {
                            teacherFields.style.display = 'block';
                        } else {
                            teacherFields.style.display = 'none';
                        }
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 