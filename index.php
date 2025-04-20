<?php 
session_start();
require_once 'config.php'; 

// Получение статистики с платформы
$stats = [];

// Количество курсов
$stmt = $pdo->query("SELECT COUNT(*) FROM courses");
$stats['courses'] = $stmt->fetchColumn();

// Количество преподавателей
$stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
$stats['teachers'] = $stmt->fetchColumn();

// Количество уроков
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons");
$stats['lessons'] = $stmt->fetchColumn();

// Количество студентов
$stmt = $pdo->query("SELECT COUNT(*) FROM accounts WHERE role = 'student'");
$stats['students'] = $stmt->fetchColumn();

// Получение нескольких популярных курсов
$stmt = $pdo->query("
    SELECT c.course_id, c.title, c.description, c.price, 
           CONCAT(t.first_name, ' ', t.last_name) as teacher_name
    FROM courses c
    JOIN teachers t ON c.teacher_id = t.teacher_id
    WHERE c.status = 'published'
    ORDER BY c.created_at DESC
    LIMIT 3
");
$featuredCourses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная | Платформа онлайн образования</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        
        nav ul li {
            margin-left: 1.5rem;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        nav ul li a:hover {
            color: #3498db;
        }
        
        .auth-buttons {
            display: flex;
            gap: 1rem;
        }
        
        button, .btn {
            padding: 0.5rem 1rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        button:hover, .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #e74c3c;
        }
        
        .btn-secondary:hover {
            background-color: #c0392b;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #3498db;
            color: #3498db;
        }
        
        .btn-outline:hover {
            background-color: #3498db;
            color: white;
        }
        
        .hero {
            padding: 4rem 0;
            background-color: #34495e;
            color: white;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features {
            padding: 4rem 0;
            background-color: white;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            padding: 1.5rem;
            border-radius: 5px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .feature-card i {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 1rem;
        }
        
        .feature-card h3 {
            margin-top: 0;
            font-size: 1.5rem;
        }
        
        .statistics {
            padding: 4rem 0;
            background-color: #ecf0f1;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            padding: 1.5rem;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3498db;
            margin: 1rem 0;
        }
        
        .featured-courses {
            padding: 4rem 0;
            background-color: white;
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .course-card {
            border-radius: 5px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            background-color: white;
            transition: transform 0.3s;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
        }
        
        .course-image {
            height: 200px;
            background-color: #ecf0f1;
            background-size: cover;
            background-position: center;
        }
        
        .course-content {
            padding: 1.5rem;
        }
        
        .course-content h3 {
            margin-top: 0;
            font-size: 1.3rem;
        }
        
        .teacher-name {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .course-description {
            margin-bottom: 1.5rem;
        }
        
        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .price {
            font-weight: bold;
            color: #2c3e50;
        }
        
        section h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #2c3e50;
        }
        
        footer {
            background-color: #2c3e50;
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        
        .footer-nav {
            margin-bottom: 1rem;
        }
        
        .footer-nav a {
            color: white;
            text-decoration: none;
            margin: 0 0.5rem;
        }
        
        .copyright {
            font-size: 0.9rem;
            color: #bdc3c7;
        }
        
        .user-panel {
            background-color: #ecf0f1;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-greeting {
            font-weight: bold;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            right: 0;
        }
        
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 1rem;
                flex-direction: column;
                align-items: center;
            }
            
            nav ul li {
                margin: 0.5rem 0;
            }
            
            .auth-buttons {
                margin-top: 1rem;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>Онлайн Образование</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="index.php">Главная</a></li>
                        <li><a href="courses.php">Каталог курсов</a></li>
                        <li><a href="filtered_courses.php">Фильтр курсов</a></li>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                            <li><a href="admin/index.php">Админ-панель</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="pages/student_login.php" class="btn">Войти как студент</a>
                        <a href="pages/teacher_login.php" class="btn btn-secondary">Войти как преподаватель</a>
                        <a href="admin/login.php" class="btn btn-outline">Админ</a>
                    <?php else: ?>
                        <div class="dropdown">
                            <button class="btn">Личный кабинет</button>
                            <div class="dropdown-content">
                                <?php if ($_SESSION['role'] === 'student'): ?>
                                    <a href="pages/student_dashboard.php">Кабинет студента</a>
                                <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                                    <a href="pages/teacher_dashboard.php">Кабинет преподавателя</a>
                                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                    <a href="admin/index.php">Админ-панель</a>
                                <?php endif; ?>
                                <a href="pages/logout.php">Выйти</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="container">
        <div class="user-panel">
            <div class="user-greeting">
                Здравствуйте, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </div>
            <div>
                <?php if ($_SESSION['role'] === 'student'): ?>
                    <a href="pages/student_dashboard.php" class="btn">Кабинет студента</a>
                <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                    <a href="pages/teacher_dashboard.php" class="btn">Кабинет преподавателя</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin/index.php" class="btn">Админ-панель</a>
                <?php endif; ?>
                <a href="pages/logout.php" class="btn btn-secondary">Выйти</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <section class="hero">
        <div class="container">
            <h1>Обучайтесь у лучших преподавателей</h1>
            <p>Наша платформа предлагает широкий выбор курсов от опытных преподавателей. Начните свое обучение сегодня!</p>
            <a href="courses.php" class="btn">Просмотреть курсы</a>
        </div>
    </section>
    
    <section class="features">
        <div class="container">
            <h2>Преимущества нашей платформы</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <h3>Качественное обучение</h3>
                    <p>Все курсы разработаны опытными преподавателями и экспертами в своих областях.</p>
                </div>
                <div class="feature-card">
                    <h3>Удобный доступ</h3>
                    <p>Учитесь в любое время и в любом месте, с любого устройства.</p>
                </div>
                <div class="feature-card">
                    <h3>Сертификация</h3>
                    <p>Получайте сертификаты по окончании курсов, которые признаются работодателями.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="statistics">
        <div class="container">
            <h2>Наша статистика</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Курсы</h3>
                    <div class="number"><?php echo $stats['courses']; ?></div>
                    <p>Доступно курсов</p>
                </div>
                <div class="stat-card">
                    <h3>Преподаватели</h3>
                    <div class="number"><?php echo $stats['teachers']; ?></div>
                    <p>Опытных преподавателей</p>
                </div>
                <div class="stat-card">
                    <h3>Уроки</h3>
                    <div class="number"><?php echo $stats['lessons']; ?></div>
                    <p>Уроков для обучения</p>
                </div>
                <div class="stat-card">
                    <h3>Студенты</h3>
                    <div class="number"><?php echo $stats['students']; ?></div>
                    <p>Обучающихся студентов</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="featured-courses">
        <div class="container">
            <h2>Популярные курсы</h2>
            <div class="courses-grid">
                <?php foreach ($featuredCourses as $course): ?>
                <div class="course-card">
                    <div class="course-image" style="background-image: url('images/course_default.jpg')"></div>
                    <div class="course-content">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <div class="teacher-name">Преподаватель: <?php echo htmlspecialchars($course['teacher_name']); ?></div>
                        <div class="course-description">
                            <?php echo substr(htmlspecialchars($course['description']), 0, 100) . '...'; ?>
                        </div>
                        <div class="course-footer">
                            <span class="price"><?php echo $course['price'] > 0 ? number_format($course['price'], 0, '.', ' ') . ' руб.' : 'Бесплатно'; ?></span>
                            <a href="pages/course_view.php?id=<?php echo $course['course_id']; ?>" class="btn">Подробнее</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($featuredCourses)): ?>
                <div class="no-courses">
                    <p>Пока нет доступных курсов. Проверьте позже!</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="courses.php" class="btn">Все курсы</a>
            </div>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="footer-nav">
                <a href="index.php">Главная</a>
                <a href="courses.php">Курсы</a>
                <a href="#">О нас</a>
                <a href="#">Контакты</a>
                <a href="#">Политика конфиденциальности</a>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Платформа онлайн образования. Все права защищены.
            </div>
        </div>
    </footer>
</body>
</html> 