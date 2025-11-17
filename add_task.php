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

$error = '';
$success = '';

// Get categories for dropdown
$categories = [];
try {
    $cat_query = "SELECT id, name, color FROM categories WHERE user_id = :user_id ORDER BY name";
    $stmt = $db->prepare($cat_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $error = "Error loading categories: " . $exception->getMessage();
}

// Handle form submission
if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    // Validation
    if (empty($title)) {
        $error = 'Judul tugas harus diisi!';
    } elseif (empty($due_date)) {
        $error = 'Tanggal jatuh tempo harus diisi!';
    } elseif (strtotime($due_date) < strtotime(date('Y-m-d'))) {
        $error = 'Tanggal jatuh tempo tidak boleh di masa lalu!';
    } else {
        try {
            // Insert new task
            $query = "INSERT INTO tasks (user_id, category_id, title, description, status, priority, due_date, created_at) 
                     VALUES (:user_id, :category_id, :title, :description, :status, :priority, :due_date, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':priority', $priority);
            $stmt->bindParam(':due_date', $due_date);
            
            if ($stmt->execute()) {
                $success = 'Tugas berhasil ditambahkan!';
                
                // Reset form
                $_POST = array();
            } else {
                $error = 'Gagal menambahkan tugas. Silakan coba lagi.';
            }
        } catch(PDOException $exception) {
            $error = 'Terjadi kesalahan sistem: ' . $exception->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Tugas - To-Do List App</title>
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
            max-width: 1200px;
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
            max-width: 800px;
            margin: clamp(1rem, 3vh, 2rem) auto;
            padding: 0 clamp(0.5rem, 2vw, 1.5rem);
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: var(--radius);
            padding: clamp(1.5rem, 3vw, 2.5rem);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .form-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .form-header {
            text-align: center;
            margin-bottom: clamp(1.5rem, 3vh, 2rem);
        }

        .form-title {
            font-size: clamp(1.3rem, 3vw, 1.8rem);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .form-subtitle {
            color: var(--gray);
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        /* Alerts */
        .alert {
            padding: clamp(0.8rem, 2vw, 1rem);
            border-radius: var(--radius-sm);
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            display: none;
        }

        .alert.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: clamp(1.2rem, 2.5vh, 1.8rem);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        .form-label .required {
            color: var(--error);
        }

        .form-control {
            width: 100%;
            padding: clamp(0.75rem, 2vw, 1rem) clamp(1rem, 2vw, 1.2rem);
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: clamp(0.9rem, 2vw, 1rem);
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-control.error {
            border-color: var(--error);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: clamp(1rem, 2vw, 1.5rem);
            margin-bottom: clamp(1.2rem, 2.5vh, 1.8rem);
        }

        /* Radio & Checkbox Groups */
        .radio-group, .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .radio-option, .checkbox-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .radio-option input, .checkbox-option input {
            margin: 0;
        }

        .priority-option {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
            min-width: 100px;
            text-align: center;
        }

        .priority-option:hover {
            border-color: var(--primary);
        }

        .priority-option input:checked + .priority-label {
            font-weight: 600;
        }

        .priority-low input:checked + .priority-label {
            color: var(--success);
            border-color: var(--success);
        }

        .priority-medium input:checked + .priority-label {
            color: var(--warning);
            border-color: var(--warning);
        }

        .priority-high input:checked + .priority-label {
            color: var(--error);
            border-color: var(--error);
        }

        .priority-label {
            display: block;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: clamp(1.5rem, 3vh, 2.5rem);
            flex-wrap: wrap;
        }

        .btn {
            padding: clamp(0.75rem, 2vw, 1rem) clamp(1.5rem, 3vw, 2rem);
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: clamp(0.9rem, 2vw, 1rem);
            justify-content: center;
            text-align: center;
            flex: 1;
            min-width: 140px;
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

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--dark);
        }

        .btn-outline:hover {
            background: var(--light);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* Character Counter */
        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .char-counter.warning {
            color: var(--warning);
        }

        .char-counter.error {
            color: var(--error);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-10px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .logo span {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .form-card {
                padding: 1.2rem;
            }
            
            .radio-group, .checkbox-group {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .priority-option {
                min-width: auto;
            }
        }

        /* Loading State */
        .btn-loading {
            position: relative;
            color: transparent;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Helper Text */
        .helper-text {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.25rem;
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
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="add_task.php" class="active"><i class="fas fa-plus"></i> Tambah Tugas</a>
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
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="add_task.php" class="active"><i class="fas fa-plus"></i> Tambah Tugas</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1 class="form-title">
                    <i class="fas fa-plus-circle"></i>
                    Tambah Tugas Baru
                </h1>
                <p class="form-subtitle">Isi form berikut untuk menambahkan tugas baru ke dalam daftar Anda</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error show">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success show">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo htmlspecialchars($success); ?>
                    <br><small>Anda akan diarahkan ke dashboard dalam 3 detik...</small>
                </div>
                <?php 
                if ($success) {
                    echo '<script>setTimeout(() => { window.location.href = "dashboard.php"; }, 3000);</script>';
                }
                ?>
            <?php endif; ?>

            <form id="taskForm" method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="title">
                        Judul Tugas <span class="required">*</span>
                    </label>
                    <input type="text" class="form-control" id="title" name="title" 
                           placeholder="Masukkan judul tugas" 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                           required maxlength="255">
                    <div class="char-counter" id="titleCounter">
                        <span id="titleCount">0</span>/255 karakter
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Deskripsi Tugas</label>
                    <textarea class="form-control" id="description" name="description" 
                              placeholder="Tambahkan deskripsi detail tentang tugas ini (opsional)"
                              rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <div class="char-counter" id="descCounter">
                        <span id="descCount">0</span>/1000 karakter
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="category_id">Kategori</label>
                        <select class="form-control form-select" id="category_id" name="category_id">
                            <option value="">Pilih Kategori (Opsional)</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>
                                    style="color: <?php echo $category['color']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="helper-text">Buat kategori baru dari halaman profile</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="due_date">
                            Tanggal Jatuh Tempo <span class="required">*</span>
                        </label>
                        <input type="date" class="form-control" id="due_date" name="due_date" 
                               value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''; ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                        <div class="helper-text">Pilih tanggal paling cepat hari ini</div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Prioritas</label>
                        <div class="radio-group">
                            <label class="priority-option priority-low">
                                <input type="radio" name="priority" value="low" 
                                    <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'low') ? 'checked' : ''; ?>>
                                <span class="priority-label">
                                    <i class="fas fa-arrow-down"></i> Rendah
                                </span>
                            </label>
                            <label class="priority-option priority-medium">
                                <input type="radio" name="priority" value="medium" 
                                    <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'medium') ? 'checked' : ''; ?>>
                                <span class="priority-label">
                                    <i class="fas fa-minus"></i> Sedang
                                </span>
                            </label>
                            <label class="priority-option priority-high">
                                <input type="radio" name="priority" value="high" 
                                    <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'checked' : ''; ?>>
                                <span class="priority-label">
                                    <i class="fas fa-arrow-up"></i> Tinggi
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="status" value="pending" 
                                    <?php echo (!isset($_POST['status']) || $_POST['status'] == 'pending') ? 'checked' : ''; ?>>
                                <span>Pending</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="status" value="in_progress" 
                                    <?php echo (isset($_POST['status']) && $_POST['status'] == 'in_progress') ? 'checked' : ''; ?>>
                                <span>In Progress</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Simpan Tugas
                    </button>
                    <a href="dashboard.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="reset" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                </div>
            </form>
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

        // Character counters
        const titleInput = document.getElementById('title');
        const descInput = document.getElementById('description');
        const titleCount = document.getElementById('titleCount');
        const descCount = document.getElementById('descCount');
        const titleCounter = document.getElementById('titleCounter');
        const descCounter = document.getElementById('descCounter');

        function updateCounter(input, counter, max) {
            const length = input.value.length;
            counter.textContent = length;
            
            if (length > max * 0.9) {
                counter.classList.add('error');
            } else if (length > max * 0.75) {
                counter.classList.add('warning');
                counter.classList.remove('error');
            } else {
                counter.classList.remove('warning', 'error');
            }
        }

        titleInput.addEventListener('input', () => {
            updateCounter(titleInput, titleCount, 255);
        });

        descInput.addEventListener('input', () => {
            updateCounter(descInput, descCount, 1000);
        });

        // Initialize counters
        updateCounter(titleInput, titleCount, 255);
        updateCounter(descInput, descCount, 1000);

        // Form validation
        const taskForm = document.getElementById('taskForm');
        const submitBtn = document.getElementById('submitBtn');

        taskForm.addEventListener('submit', function(e) {
            const title = titleInput.value.trim();
            const dueDate = document.getElementById('due_date').value;

            if (!title) {
                e.preventDefault();
                showError('Judul tugas harus diisi!');
                titleInput.focus();
                return;
            }

            if (!dueDate) {
                e.preventDefault();
                showError('Tanggal jatuh tempo harus diisi!');
                document.getElementById('due_date').focus();
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="btn-loading"></div> Menyimpan...';
        });

        function showError(message) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            // Create new error alert
            const alert = document.createElement('div');
            alert.className = 'alert alert-error show';
            alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
            
            // Insert after form header
            const formHeader = document.querySelector('.form-header');
            formHeader.parentNode.insertBefore(alert, formHeader.nextSibling);

            // Auto remove after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Reset form confirmation
        const resetBtn = document.getElementById('resetBtn');
        resetBtn.addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin mengosongkan semua field?')) {
                e.preventDefault();
            }
        });

        // Set minimum date to today
        const dueDateInput = document.getElementById('due_date');
        if (!dueDateInput.value) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            dueDateInput.value = tomorrow.toISOString().split('T')[0];
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Close mobile menu on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // Add some interactive features
        document.querySelectorAll('.priority-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove checked state from all options
                document.querySelectorAll('.priority-option input').forEach(input => {
                    input.checked = false;
                });
                
                // Set checked state for clicked option
                this.querySelector('input').checked = true;
            });
        });

        // Auto-save draft (localStorage)
        function saveDraft() {
            const formData = {
                title: titleInput.value,
                description: descInput.value,
                category_id: document.getElementById('category_id').value,
                due_date: dueDateInput.value,
                priority: document.querySelector('input[name="priority"]:checked')?.value,
                status: document.querySelector('input[name="status"]:checked')?.value
            };
            localStorage.setItem('taskDraft', JSON.stringify(formData));
        }

        function loadDraft() {
            const draft = localStorage.getItem('taskDraft');
            if (draft) {
                const formData = JSON.parse(draft);
                titleInput.value = formData.title || '';
                descInput.value = formData.description || '';
                document.getElementById('category_id').value = formData.category_id || '';
                dueDateInput.value = formData.due_date || '';
                
                if (formData.priority) {
                    document.querySelector(`input[name="priority"][value="${formData.priority}"]`).checked = true;
                }
                if (formData.status) {
                    document.querySelector(`input[name="status"][value="${formData.status}"]`).checked = true;
                }
                
                // Update counters
                updateCounter(titleInput, titleCount, 255);
                updateCounter(descInput, descCount, 1000);
            }
        }

        // Auto-save when user types
        titleInput.addEventListener('input', saveDraft);
        descInput.addEventListener('input', saveDraft);
        dueDateInput.addEventListener('change', saveDraft);
        document.getElementById('category_id').addEventListener('change', saveDraft);
        document.querySelectorAll('input[name="priority"]').forEach(input => {
            input.addEventListener('change', saveDraft);
        });
        document.querySelectorAll('input[name="status"]').forEach(input => {
            input.addEventListener('change', saveDraft);
        });

        // Load draft on page load
        window.addEventListener('load', loadDraft);

        // Clear draft when form is successfully submitted
        taskForm.addEventListener('submit', function() {
            localStorage.removeItem('taskDraft');
        });
    </script>
</body>
</html>