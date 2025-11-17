<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Redirect jika sudah login
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: ../dashboard.php");
    exit();
}

if ($_POST) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi input
    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Semua field harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } elseif (strlen($password) < 6) {
        $error = 'Password harus minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($username) < 3) {
        $error = 'Username harus minimal 3 karakter!';
    } else {
        try {
            // Cek apakah username sudah digunakan
            $check_username = "SELECT id FROM users WHERE username = :username";
            $stmt = $db->prepare($check_username);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Username sudah digunakan!';
            } else {
                // Cek apakah email sudah digunakan
                $check_email = "SELECT id FROM users WHERE email = :email";
                $stmt = $db->prepare($check_email);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = 'Email sudah terdaftar!';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user baru
                    $query = "INSERT INTO users (username, email, password, full_name, created_at) 
                             VALUES (:username, :email, :password, :full_name, NOW())";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':full_name', $full_name);
                    
                    if ($stmt->execute()) {
                        $success = 'Pendaftaran berhasil! Silakan login.';
                        
                        // Reset form
                        $_POST = array();
                    } else {
                        $error = 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.';
                    }
                }
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
    <title>Daftar - To-Do List App</title>
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
            --error: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            width: 100%;
            max-width: 480px;
        }

        .register-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-label .required {
            color: var(--error);
        }

        .input-group {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-control.error {
            border-color: var(--error);
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: var(--gray);
            cursor: not-allowed;
            transform: none;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--gray);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: none;
        }

        .alert.show {
            display: block;
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

        .password-toggle {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .password-strength {
            margin-top: 8px;
            font-size: 0.85rem;
        }

        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: var(--transition);
        }

        .strength-weak { background: var(--error); width: 33%; }
        .strength-medium { background: #ffb703; width: 66%; }
        .strength-strong { background: var(--success); width: 100%; }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .register-container {
                max-width: 100%;
            }
            
            .card-header {
                padding: 25px 20px;
            }
            
            .card-body {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="card-header">
                <h1>Daftar Akun Baru</h1>
                <p>Bergabung dengan To-Do List App</p>
            </div>
            
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error show">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success show">
                        <?php echo htmlspecialchars($success); ?>
                        <br><small>Anda akan diarahkan ke halaman login dalam 5 detik...</small>
                    </div>
                    <?php 
                    if ($success) {
                        echo '<script>setTimeout(() => { window.location.href = "login.php"; }, 5000);</script>';
                    }
                    ?>
                <?php endif; ?>
                
                <form id="registerForm" method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="full_name">
                            Nama Lengkap <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   placeholder="Masukkan nama lengkap Anda" 
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                                   required>
                            <span class="input-icon">👤</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="username">
                            Username <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Masukkan username (min. 3 karakter)" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required minlength="3">
                            <span class="input-icon">🔧</span>
                        </div>
                        <small style="color: var(--gray); font-size: 0.85rem;">Username akan digunakan untuk login</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">
                            Email <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Masukkan alamat email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                            <span class="input-icon">📧</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">
                            Password <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Masukkan password (min. 6 karakter)" 
                                   required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">👁️</button>
                            <span class="input-icon">🔒</span>
                        </div>
                        <div class="password-strength">
                            <span id="passwordStrengthText">Kekuatan password</span>
                            <div class="strength-bar">
                                <div class="strength-fill" id="passwordStrengthBar"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">
                            Konfirmasi Password <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Masukkan ulang password" 
                                   required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">👁️</button>
                            <span class="input-icon">🔒</span>
                        </div>
                        <small id="passwordMatch" style="font-size: 0.85rem;"></small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="registerBtn">
                        <span id="btnText">Daftar</span>
                        <div id="btnSpinner" class="spinner" style="display: none;"></div>
                    </button>
                </form>
                
                <div class="login-link">
                    Sudah punya akun? <a href="login.php">Masuk di sini</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleButtons = document.querySelectorAll('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButtons.forEach(btn => {
                    if (btn.onclick.toString().includes(fieldId)) {
                        btn.textContent = '🙈';
                    }
                });
            } else {
                passwordInput.type = 'password';
                toggleButtons.forEach(btn => {
                    if (btn.onclick.toString().includes(fieldId)) {
                        btn.textContent = '👁️';
                    }
                });
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');

            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;

            // Reset classes
            strengthBar.className = 'strength-fill';

            if (password.length === 0) {
                strengthText.textContent = 'Kekuatan password';
                strengthText.style.color = 'var(--gray)';
                strengthBar.style.width = '0%';
            } else if (strength <= 2) {
                strengthText.textContent = 'Lemah';
                strengthText.style.color = 'var(--error)';
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthText.textContent = 'Sedang';
                strengthText.style.color = '#ffb703';
                strengthBar.classList.add('strength-medium');
            } else {
                strengthText.textContent = 'Kuat';
                strengthText.style.color = 'var(--success)';
                strengthBar.classList.add('strength-strong');
            }
        }

        // Check password match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.style.color = '';
            } else if (password === confirmPassword) {
                matchText.textContent = '✓ Password cocok';
                matchText.style.color = 'var(--success)';
            } else {
                matchText.textContent = '✗ Password tidak cocok';
                matchText.style.color = 'var(--error)';
            }
        }

        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        // Form submission handler
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const registerBtn = document.getElementById('registerBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');

            // Client-side validation
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password harus minimal 6 karakter!');
                return;
            }

            // Show loading state
            btnText.style.display = 'none';
            btnSpinner.style.display = 'block';
            registerBtn.disabled = true;
        });

        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>