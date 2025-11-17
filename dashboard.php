<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: auth/login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user's tasks
$tasks = [];
$task_stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'overdue' => 0
];

try {
    // Query untuk mengambil tasks user
    $query = "SELECT t.*, c.name as category_name, c.color as category_color 
              FROM tasks t 
              LEFT JOIN categories c ON t.category_id = c.id 
              WHERE t.user_id = :user_id 
              ORDER BY 
                CASE 
                  WHEN t.status = 'completed' THEN 3
                  WHEN t.due_date < NOW() AND t.status != 'completed' THEN 0
                  ELSE 1 
                END,
                t.due_date ASC,
                t.priority DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk statistics
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN due_date < NOW() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
                    FROM tasks 
                    WHERE user_id = :user_id";
    
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $exception) {
    $error = "Error loading tasks: " . $exception->getMessage();
}

// Get categories for filter
$categories = [];
try {
    $cat_query = "SELECT id, name, color FROM categories WHERE user_id = :user_id";
    $stmt = $db->prepare($cat_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    // Skip category error
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - To-Do List App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #2a9d8f;
            --warning: #f4a261;
            --error: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            background: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        /* Header & Navigation */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .logo {
            font-size: clamp(1.1rem, 2.5vw, 1.5rem);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: clamp(1.3rem, 2.5vw, 1.8rem);
        }

        /* Desktop Navigation */
        .nav-links {
            display: flex;
            gap: clamp(0.5rem, 2vw, 1.5rem);
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: clamp(0.4rem, 1.5vw, 0.6rem) clamp(0.8rem, 2vw, 1.2rem);
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(0.8rem, 1.5vw, 0.95rem);
            white-space: nowrap;
        }

        .nav-links a:hover, .nav-links a.active {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }

        /* Mobile Navigation */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: clamp(1.3rem, 4vw, 1.8rem);
            cursor: pointer;
            padding: 0.5rem;
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            transform: scale(1.1);
        }

        .mobile-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 999;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: clamp(1.5rem, 4vh, 2.5rem);
            backdrop-filter: blur(10px);
        }

        .mobile-nav.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .mobile-nav a {
            color: white;
            text-decoration: none;
            font-size: clamp(1.1rem, 4vw, 1.4rem);
            padding: clamp(0.8rem, 2vh, 1.2rem) clamp(1.5rem, 4vw, 2.5rem);
            border-radius: var(--radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
            width: min(90%, 300px);
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .mobile-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(10px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .close-menu {
            position: absolute;
            top: clamp(1.5rem, 4vh, 2.5rem);
            right: clamp(1.5rem, 4vw, 2.5rem);
            background: none;
            border: none;
            color: white;
            font-size: clamp(1.5rem, 4vw, 2.2rem);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-menu:hover {
            transform: rotate(90deg);
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: clamp(1rem, 3vh, 2rem) auto;
            padding: 0 clamp(0.5rem, 2vw, 1.5rem);
            display: grid;
            grid-template-columns: minmax(250px, 320px) 1fr;
            gap: clamp(1rem, 3vw, 2rem);
            align-items: start;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: var(--radius);
            padding: clamp(1rem, 2vw, 1.8rem);
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: clamp(5rem, 10vh, 7rem);
        }

        .user-info {
            text-align: center;
            padding-bottom: clamp(1rem, 2vh, 1.5rem);
            border-bottom: 1px solid var(--border);
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
        }

        .user-avatar {
            width: clamp(60px, 8vw, 85px);
            height: clamp(60px, 8vw, 85px);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto clamp(0.8rem, 1.5vh, 1.2rem);
            color: white;
            font-size: clamp(1.3rem, 3vw, 1.8rem);
            box-shadow: var(--shadow);
        }

        .user-info h3 {
            font-size: clamp(0.95rem, 2vw, 1.2rem);
            margin-bottom: 0.25rem;
            word-break: break-word;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: clamp(0.6rem, 1.5vw, 1rem);
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
        }

        .stat-card {
            background: var(--light);
            padding: clamp(0.7rem, 1.5vw, 1rem);
            border-radius: var(--radius-sm);
            text-align: center;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--border);
        }

        .stat-number {
            font-size: clamp(1.1rem, 2.5vw, 1.5rem);
            font-weight: 700;
            display: block;
            line-height: 1.2;
        }

        .stat-label {
            font-size: clamp(0.7rem, 1.5vw, 0.85rem);
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .total { color: var(--primary); }
        .pending { color: var(--warning); }
        .completed { color: var(--success); }
        .overdue { color: var(--error); }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: clamp(1rem, 2.5vh, 1.8rem);
            min-width: 0; /* Prevent flex item overflow */
        }

        .welcome-card, .tasks-card {
            background: white;
            border-radius: var(--radius);
            padding: clamp(1.2rem, 2.5vw, 1.8rem);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .welcome-card:hover, .tasks-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-title {
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
            font-weight: 600;
            color: var(--dark);
            line-height: 1.3;
        }

        /* Buttons */
        .btn {
            padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(0.8rem, 2vw, 1.2rem);
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-error { background: var(--error); color: white; }

        .btn-success:hover, .btn-warning:hover, .btn-error:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            filter: brightness(1.1);
        }

        /* Filters */
        .filters {
            display: flex;
            gap: clamp(0.5rem, 1.5vw, 1rem);
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
            flex-wrap: wrap;
        }

        .filter-select {
            padding: clamp(0.5rem, 1.5vw, 0.7rem);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: white;
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
            min-width: min(140px, 100%);
            flex: 1;
            transition: var(--transition);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Tasks List */
        .tasks-list {
            display: flex;
            flex-direction: column;
            gap: clamp(0.8rem, 2vh, 1.2rem);
        }

        .task-item {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: clamp(1rem, 2vw, 1.4rem);
            transition: var(--transition);
            background: white;
            animation: slideIn 0.3s ease;
        }

        .task-item:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .task-item.overdue {
            border-left: 4px solid var(--error);
            background: linear-gradient(90deg, rgba(230, 57, 70, 0.03) 0%, white 5%);
        }

        .task-item.completed {
            opacity: 0.8;
            background: linear-gradient(90deg, rgba(42, 157, 143, 0.05) 0%, white 5%);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: clamp(0.6rem, 1.5vh, 0.9rem);
            gap: 1rem;
        }

        .task-title {
            font-weight: 600;
            font-size: clamp(1rem, 2vw, 1.2rem);
            margin-bottom: 0.5rem;
            line-height: 1.4;
            color: var(--dark);
            word-break: break-word;
        }

        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(0.5rem, 1.5vw, 0.8rem);
            font-size: clamp(0.75rem, 1.5vw, 0.85rem);
            color: var(--gray);
            margin-bottom: 0.5rem;
            align-items: center;
        }

        .task-category {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: clamp(0.65rem, 1.5vw, 0.75rem);
            font-weight: 500;
            color: white;
            white-space: nowrap;
        }

        .priority-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: clamp(0.65rem, 1.5vw, 0.75rem);
            font-weight: 500;
            white-space: nowrap;
        }

        .priority-high { background: var(--error); color: white; }
        .priority-medium { background: var(--warning); color: white; }
        .priority-low { background: var(--success); color: white; }

        .task-actions {
            display: flex;
            gap: clamp(0.4rem, 1vw, 0.6rem);
            margin-top: clamp(0.8rem, 2vh, 1.2rem);
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: clamp(0.35rem, 1vw, 0.5rem) clamp(0.6rem, 1.5vw, 0.9rem);
            font-size: clamp(0.75rem, 1.5vw, 0.8rem);
            border-radius: var(--radius-sm);
            flex: 1;
            min-width: fit-content;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: clamp(2rem, 5vh, 4rem);
            color: var(--gray);
        }

        .empty-state i {
            font-size: clamp(2.5rem, 6vw, 4rem);
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            font-size: clamp(1.1rem, 3vw, 1.4rem);
        }

        .empty-state p {
            font-size: clamp(0.9rem, 2vw, 1rem);
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(10px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== RESPONSIVE BREAKPOINTS ===== */

        /* Large Desktop (1400px+) */
        @media (min-width: 1400px) {
            .container {
                grid-template-columns: 320px 1fr;
            }
        }

        /* Medium Desktop (1024px - 1399px) */
        @media (max-width: 1199px) {
            .container {
                grid-template-columns: minmax(240px, 280px) 1fr;
                gap: 1.5rem;
            }
        }

        /* Small Desktop & Tablet Landscape (768px - 1023px) */
        @media (max-width: 1023px) {
            .container {
                grid-template-columns: minmax(220px, 260px) 1fr;
                gap: 1.2rem;
            }
            
            .nav-links {
                gap: 0.8rem;
            }
            
            .nav-links a {
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
            }
        }

        /* Tablet Portrait & Large Mobile (600px - 767px) */
        @media (max-width: 767px) {
            .container {
                grid-template-columns: 1fr;
                padding: 0 0.8rem;
                gap: 1rem;
            }
            
            .sidebar {
                position: static;
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
            
            .nav-links {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .logo span {
                display: none;
            }
            
            .welcome-card, .tasks-card {
                padding: 1.2rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: stretch;
                gap: 0.8rem;
            }
            
            .filters {
                justify-content: center;
            }
            
            .filter-select {
                min-width: 120px;
            }
            
            .task-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .task-actions {
                justify-content: center;
            }
        }

        /* Mobile (480px - 599px) */
        @media (max-width: 599px) {
            .header {
                padding: 0.8rem;
            }
            
            .container {
                margin: 0.5rem auto;
                padding: 0 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 0.7rem 0.5rem;
            }
            
            .task-item {
                padding: 1rem;
            }
            
            .task-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.4rem;
            }
            
            .task-actions {
                flex-direction: column;
            }
            
            .btn-sm {
                width: 100%;
                justify-content: center;
            }
            
            .mobile-nav a {
                width: 85%;
                max-width: 280px;
            }
        }

        /* Small Mobile (320px - 479px) */
        @media (max-width: 479px) {
            .nav-container {
                padding: 0 0.5rem;
            }
            
            .logo i {
                font-size: 1.4rem;
            }
            
            .sidebar {
                padding: 1rem;
            }
            
            .user-avatar {
                width: 55px;
                height: 55px;
                font-size: 1.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-card, .tasks-card {
                padding: 1rem;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-select {
                min-width: 100%;
            }
            
            .mobile-nav a {
                width: 90%;
                padding: 1rem 1.5rem;
                font-size: 1.1rem;
            }
        }

        /* Extra Small Mobile (under 320px) */
        @media (max-width: 319px) {
            .container {
                padding: 0 0.3rem;
            }
            
            .sidebar, .welcome-card, .tasks-card {
                padding: 0.8rem;
            }
            
            .task-item {
                padding: 0.8rem;
            }
            
            .btn-sm {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
        }

        /* Print Styles */
        @media print {
            .header, .sidebar, .task-actions, .filters {
                display: none;
            }
            
            .container {
                grid-template-columns: 1fr;
                margin: 0;
                padding: 0;
            }
            
            .task-item {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-tasks"></i>
                <span>To-Do List</span>
            </div>
            
            <!-- Desktop Navigation -->
            <div class="nav-links">
                <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
    
    <!-- Mobile Navigation Menu -->
    <div class="mobile-nav" id="mobileNav">
        <button class="close-menu" id="closeMenu" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p style="color: var(--gray); font-size: clamp(0.7rem, 1.5vw, 0.85rem);">@<?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number total"><?php echo $task_stats['total']; ?></span>
                    <span class="stat-label">Total Tugas</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number pending"><?php echo $task_stats['pending']; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number completed"><?php echo $task_stats['completed']; ?></span>
                    <span class="stat-label">Selesai</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number overdue"><?php echo $task_stats['overdue']; ?></span>
                    <span class="stat-label">Terlambat</span>
                </div>
            </div>

            <a href="add_task.php" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                <i class="fas fa-plus"></i> Tambah Tugas Baru
            </a>
        </div>

        <div class="main-content">
            <div class="welcome-card">
                <h2>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h2>
                <p style="color: var(--gray); margin-top: 0.5rem; line-height: 1.5;">
                    Anda memiliki <strong><?php echo $task_stats['pending']; ?> tugas</strong> yang belum diselesaikan.
                    <?php if ($task_stats['overdue'] > 0): ?>
                        <span style="color: var(--error); display: block; margin-top: 0.5rem;">
                            <strong><?php echo $task_stats['overdue']; ?> tugas</strong> sudah melewati batas waktu.
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="tasks-card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Tugas Anda</h3>
                    <div class="filters">
                        <select class="filter-select" onchange="filterTasks('status', this.value)">
                            <option value="">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                        <select class="filter-select" onchange="filterTasks('priority', this.value)">
                            <option value="">Semua Prioritas</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="tasks-list" id="tasksList">
                    <?php if (empty($tasks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <h3>Belum ada tugas</h3>
                            <p>Mulai dengan menambahkan tugas pertama Anda!</p>
                            <a href="add_task.php" class="btn btn-primary" style="margin-top: 1rem; justify-content: center;">
                                <i class="fas fa-plus"></i> Tambah Tugas Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): 
                            $isOverdue = strtotime($task['due_date']) < time() && $task['status'] != 'completed';
                            $statusClass = $task['status'] == 'completed' ? 'completed' : ($isOverdue ? 'overdue' : '');
                        ?>
                            <div class="task-item <?php echo $statusClass; ?>" 
                                 data-status="<?php echo $task['status']; ?>"
                                 data-priority="<?php echo $task['priority']; ?>">
                                <div class="task-header">
                                    <div style="flex: 1;">
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-meta">
                                            <?php if ($task['category_name']): ?>
                                                <span class="task-category" style="background: <?php echo $task['category_color'] ?: '#6c757d'; ?>">
                                                    <?php echo htmlspecialchars($task['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                            <span><i class="far fa-calendar"></i> 
                                                <?php echo date('d M Y', strtotime($task['due_date'])); ?>
                                            </span>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <p style="color: var(--gray); font-size: clamp(0.8rem, 1.5vw, 0.9rem); margin-top: 0.5rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($task['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="color: var(--gray); font-size: clamp(0.75rem, 1.5vw, 0.85rem); margin-top: 0.5rem;">
                                        <?php 
                                        $statusText = [
                                            'pending' => 'Pending',
                                            'in_progress' => 'In Progress', 
                                            'completed' => 'Selesai'
                                        ];
                                        echo $statusText[$task['status']];
                                        ?>
                                    </div>
                                </div>
                                <div class="task-actions">
                                    <?php if ($task['status'] != 'completed'): ?>
                                        <button class="btn btn-success btn-sm" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'completed')">
                                            <i class="fas fa-check"></i> Selesai
                                        </button>
                                        <?php if ($task['status'] == 'pending'): ?>
                                            <button class="btn btn-warning btn-sm" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')">
                                                <i class="fas fa-play"></i> Start
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-sm" onclick="editTask(<?php echo $task['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-error btn-sm" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileNav = document.getElementById('mobileNav');
        const closeMenu = document.getElementById('closeMenu');

        function openMobileMenu() {
            mobileNav.classList.add('active');
            document.body.style.overflow = 'hidden';
            mobileMenuBtn.setAttribute('aria-expanded', 'true');
        }

        function closeMobileMenu() {
            mobileNav.classList.remove('active');
            document.body.style.overflow = 'auto';
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
        }

        mobileMenuBtn.addEventListener('click', openMobileMenu);
        closeMenu.addEventListener('click', closeMobileMenu);

        // Close menu when clicking on links
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });

        // Close menu when clicking outside
        mobileNav.addEventListener('click', (e) => {
            if (e.target === mobileNav) {
                closeMobileMenu();
            }
        });

        // Task Functions
        function filterTasks(type, value) {
            const tasks = document.querySelectorAll('.task-item');
            tasks.forEach(task => {
                const taskValue = task.getAttribute('data-' + type);
                if (!value || taskValue === value) {
                    task.style.display = 'block';
                } else {
                    task.style.display = 'none';
                }
            });
        }

        function updateTaskStatus(taskId, status) {
            if (confirm('Apakah Anda yakin ingin mengubah status tugas?')) {
                // Implement AJAX call to update task status
                alert('Fitur update status akan diimplementasikan! Task ID: ' + taskId + ', Status: ' + status);
            }
        }

        function editTask(taskId) {
            // Implement edit functionality
            alert('Fitur edit akan diimplementasikan! Task ID: ' + taskId);
        }

        function deleteTask(taskId) {
            if (confirm('Apakah Anda yakin ingin menghapus tugas ini?')) {
                // Implement AJAX call to delete task
                alert('Fitur hapus akan diimplementasikan! Task ID: ' + taskId);
            }
        }

        // Close mobile menu on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // Touch gesture support for mobile
        let startX = 0;
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });

        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const diffX = startX - endX;
            
            // Swipe right to close menu
            if (diffX > 50 && mobileNav.classList.contains('active')) {
                closeMobileMenu();
            }
        });

        // Prevent zoom on double tap for mobile
        let lastTap = 0;
        document.addEventListener('touchend', (e) => {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTap;
            if (tapLength < 500 && tapLength > 0) {
                e.preventDefault();
            }
            lastTap = currentTime;
        });
    </script>
</body>
</html>