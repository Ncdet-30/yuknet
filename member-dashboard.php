<?php
session_start();
require_once __DIR__ . '/includes/AuthMiddleware.php';
require_once __DIR__ . '/config/Database.php';

// Üye giriş kontrolü
AuthMiddleware::requireMember();
AuthMiddleware::checkSessionExpiry();

$database = new Database();
$pdo = $database->connect();
$memberId = $_SESSION['user_id'];

// Üye bilgilerini al
$stmt = $pdo->prepare('SELECT * FROM member_logins WHERE id = ?');
$stmt->execute([$memberId]);
$member = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Üye Dashboard - Yuknet</title>
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

        .member-info {
            text-align: right;
        }

        .member-info p {
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

        .badge {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
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
            <div class="sidebar-logo">🚚 Yuknet Üye</div>
            <ul class="sidebar-menu">
                <li><a href="/member-dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="/member-profile.php">👤 Profil</a></li>
                <li><a href="/member-listings.php">📋 İlanlar</a></li>
                <li><a href="/member-settings.php">⚙️ Ayarlar</a></li>
                <li><a href="?logout=1">🚪 Çıkış Yap</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <div>
                    <h1>Hoş Geldiniz, <?php echo escapeHTML($member['first_name']); ?>!</h1>
                </div>
                <div class="member-info">
                    <p><strong>Şirket:</strong> <?php echo escapeHTML($member['company_name'] ?? 'Belirtilmemiş'); ?></p>
                    <p><strong>Email:</strong> <?php echo escapeHTML($member['email']); ?></p>
                    <p><strong>Telefon:</strong> <?php echo escapeHTML($member['phone']); ?></p>
                    <p><strong>Üyelik Tarihi:</strong> <?php echo date('d.m.Y', strtotime($member['created_at'])); ?></p>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <h3>🏢 Profil Bilgileri</h3>
                    <p><?php echo escapeHTML($member['first_name']) . ' ' . escapeHTML($member['last_name']); ?></p>
                    <div class="stat-number">✓</div>
                    <?php if ($member['status'] === 'active'): ?>
                        <span class="badge">Aktif</span>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <h3>🔒 Email Doğrulaması</h3>
                    <p>Email durumu kontrol et</p>
                    <?php if ($member['verified_at']): ?>
                        <p style="color: #27ae60; margin-top: 10px;">✓ Doğrulanmış</p>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <h3>📱 Telefon Doğrulaması</h3>
                    <p><?php echo escapeHTML($member['phone']); ?></p>
                    <?php if ($member['phone_verified']): ?>
                        <p style="color: #27ae60; margin-top: 10px;">✓ Doğrulanmış</p>
                    <?php else: ?>
                        <p style="color: #f39c12; margin-top: 10px;">⚠ Doğrulanmamış</p>
                    <?php endif; ?>
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
