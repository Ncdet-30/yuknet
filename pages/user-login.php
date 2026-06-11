<?php
session_start();
require_once __DIR__ . '/../includes/UserLogin.php';
require_once __DIR__ . '/../config/security.php';

$userLogin = new UserLogin();
$errors = [];
$success = [];

// Formdan gelen veriler
$action = $_GET['action'] ?? 'login'; // login veya register

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token doğrulama
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Güvenlik hatası! Lütfen formu tekrar gönderin.";
    } else {
        if ($action === 'register') {
            // Kayıt formu
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');

            if ($password !== $confirmPassword) {
                $errors[] = "Şifreler eşleşmiyor";
            } else {
                if ($userLogin->register($username, $email, $password, $phone)) {
                    $success = $userLogin->getSuccess();
                    $action = 'login'; // Kaydından sonra login sayfasına yönlendir
                } else {
                    $errors = $userLogin->getErrors();
                }
            }
        } else {
            // Giriş formu
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($userLogin->login($email, $password)) {
                $success = $userLogin->getSuccess();
                // Başarılı giriş - yönlendir
                header('Location: ../dashboard.php');
                exit;
            } else {
                $errors = $userLogin->getErrors();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'register' ? 'Kayıt Ol' : 'Giriş Yap'; ?> - Yuknet Nakliyeler</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .toggle-form {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            gap: 0;
        }

        .toggle-form button {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .toggle-form button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .toggle-form button:first-child {
            border-radius: 5px 0 0 5px;
        }

        .toggle-form button:last-child {
            border-radius: 0 5px 5px 0;
        }

        .alerts {
            margin-bottom: 20px;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c00;
            border-left: 4px solid #c00;
        }

        .alert-success {
            background: #efe;
            color: #060;
            border-left: 4px solid #060;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .password-requirements {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }

        .password-requirements ul {
            margin: 8px 0 0 20px;
        }

        .password-requirements li {
            margin: 4px 0;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Yuknet</h1>
            <p>Nakliyeler İçin Güvenilir Platform</p>
        </div>

        <div class="toggle-form">
            <button onclick="toggleForm('login')" id="loginBtn" class="<?php echo $action === 'login' ? 'active' : ''; ?>">
                Giriş Yap
            </button>
            <button onclick="toggleForm('register')" id="registerBtn" class="<?php echo $action === 'register' ? 'active' : ''; ?>">
                Kayıt Ol
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alerts">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error"><?php echo escapeHTML($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alerts">
                <?php foreach ($success as $msg): ?>
                    <div class="alert alert-success"><?php echo escapeHTML($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Giriş Formu -->
        <form id="loginForm" method="POST" action="?action=login" class="<?php echo $action === 'register' ? 'hidden' : ''; ?>">
            <div class="form-group">
                <label for="login-email">Email Adresi</label>
                <input type="email" id="login-email" name="email" required placeholder="ornek@email.com">
            </div>

            <div class="form-group">
                <label for="login-password">Şifre</label>
                <input type="password" id="login-password" name="password" required placeholder="••••••••">
            </div>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <button type="submit" class="submit-btn">Giriş Yap</button>
        </form>

        <!-- Kayıt Formu -->
        <form id="registerForm" method="POST" action="?action=register" class="<?php echo $action === 'register' ? '' : 'hidden'; ?>">
            <div class="form-group">
                <label for="reg-username">Kullanıcı Adı</label>
                <input type="text" id="reg-username" name="username" required placeholder="kullanıcı adınız" minlength="3">
            </div>

            <div class="form-group">
                <label for="reg-email">Email Adresi</label>
                <input type="email" id="reg-email" name="email" required placeholder="ornek@email.com">
            </div>

            <div class="form-group">
                <label for="reg-phone">Telefon Numarası</label>
                <input type="tel" id="reg-phone" name="phone" required placeholder="+90 (5XX) XXX XXXX">
            </div>

            <div class="form-group">
                <label for="reg-password">Şifre</label>
                <input type="password" id="reg-password" name="password" required placeholder="••••••••">
                <div class="password-requirements">
                    <strong>Şifre Gereksinimleri:</strong>
                    <ul>
                        <li>✓ En az 8 karakter</li>
                        <li>✓ En az 1 büyük harf (A-Z)</li>
                        <li>✓ En az 1 küçük harf (a-z)</li>
                        <li>✓ En az 1 sayı (0-9)</li>
                        <li>✓ En az 1 özel karakter (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="reg-confirm-password">Şifre Tekrarı</label>
                <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="••••••••">
            </div>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <button type="submit" class="submit-btn">Kayıt Ol</button>
        </form>
    </div>

    <script>
        function toggleForm(formType) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginBtn = document.getElementById('loginBtn');
            const registerBtn = document.getElementById('registerBtn');

            if (formType === 'login') {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
                loginBtn.classList.add('active');
                registerBtn.classList.remove('active');
            } else {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                loginBtn.classList.remove('active');
                registerBtn.classList.add('active');
            }
        }
    </script>
</body>
</html>
