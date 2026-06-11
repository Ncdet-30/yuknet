<?php
session_start();
require_once __DIR__ . '/includes/AuthMiddleware.php';
require_once __DIR__ . '/config/Database.php';

// Kullanıcı giriş kontrolü
AuthMiddleware::requireLogin();
AuthMiddleware::checkSessionExpiry();

// Üye ise üye dashboard'a yönlendir
if ($_SESSION['user_type'] === 'member') {
    header('Location: /member-dashboard.php');
    exit;
}

$database = new Database();
$pdo = $database->connect();
$userId = $_SESSION['user_id'];

// Kullanıcı bilgilerini al
$stmt = $pdo->prepare('SELECT * FROM user_logins WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Yuknet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: #2c3e50;
            color: white;
            padding: 30px 0;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 0 20px 30px;
            font-size: 24px;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-left-color: #667eea;
        }

        .main-content {
            margin-left: 250px;
            padding: 40px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            color: #667;
            font-size: 14px;
            margin: 5px 0;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .card p {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.6;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin: 15px 0;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-logo">🚚 Yuknet</div>
            <ul class="sidebar-menu">
                <li><a href="/dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="/profile.php">👤 Profil</a></li>
                <li><a href="/settings.php">⚙️ Ayarlar</a></li>
                <li><a href="?logout=1">🚪 Çıkış Yap</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <div>
                    <h1>Hoş Geldiniz, <?php echo escapeHTML($user['username']); ?>!</h1>
                </div>
                <div class="user-info">
                    <p><strong>Email:</strong> <?php echo escapeHTML($user['email']); ?></p>
                    <p><strong>Telefon:</strong> <?php echo escapeHTML($user['phone']); ?></p>
                    <p><strong>Üyelik Tarihi:</strong> <?php echo date('d.m.Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <h3>📈 Profil Durumu</h3>
                    <p>Profiliniz %100 tamamlandı</p>
                    <div class="stat-number">✓</div>
                </div>
                <div class="card">
                    <h3>🔒 Güvenlik</h3>
                    <p>Son giriş: <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Henüz yok'; ?></p>
                </div>
                <div class="card">
                    <h3>📧 Email</h3>
                    <p><?php echo escapeHTML($user['email']); ?></p>
                    <p style="color: #27ae60; margin-top: 10px;">✓ Doğrulanmış</p>
                </div>
            </div>
        </main>
    </div>

    <?php
    if (isset($_GET['logout'])) {
        logout();
        header('Location: /');
        exit;
    }
    ?>
</body>
</html>
