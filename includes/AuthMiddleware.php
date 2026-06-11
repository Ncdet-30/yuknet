<?php
/**
 * Middleware - Oturum Kontrol
 * Kullanıcı/Üye giriş kontrolü için kullanılır
 */

require_once __DIR__ . '/../config/security.php';

class AuthMiddleware {
    /**
     * Kullanıcı girişi gerekli - Oturum kontrol
     */
    public static function requireLogin() {
        if (!isUserLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /pages/user-login.php');
            exit;
        }
    }

    /**
     * Admin kontrolü (gelecek için)
     */
    public static function requireAdmin() {
        self::requireLogin();
        if ($_SESSION['user_type'] !== 'user' || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            die('Bu sayfaya erişim yetkiniz yok.');
        }
    }

    /**
     * Üye kontrolü
     */
    public static function requireMember() {
        if (!isUserLoggedIn() || $_SESSION['user_type'] !== 'member') {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /pages/member-login.php');
            exit;
        }
    }

    /**
     * Session süresi kontrol et
     */
    public static function checkSessionExpiry() {
        if (isUserLoggedIn()) {
            $sessionTimeout = 86400 * 7; // 7 gün
            if (time() - $_SESSION['login_time'] > $sessionTimeout) {
                logout();
                header('Location: /pages/user-login.php?expired=1');
                exit;
            }
            $_SESSION['login_time'] = time();
        }
    }

    /**
     * CSRF token doğrulama
     */
    public static function verifyCsrf($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        
        if (!$token || !verifyCsrfToken($token)) {
            http_response_code(403);
            die('CSRF token doğrulaması başarısız.');
        }
    }
}
