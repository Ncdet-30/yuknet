<?php
/**
 * Güvenlik Ayarları ve Fonksiyonları
 * Yuknet.com - Nakliyeler İlan Sitesi
 */

// Session ayarlarını güvenli hale getir
session_start();
ini_set('session.httponly', 1);
ini_set('session.secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// CSRF Token oluştur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * CSRF Token Doğrulama
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF Token HTML'de Döndür
 */
function getCsrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

/**
 * Şifre hash oluştur (SHA256 + salt ile)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Şifre doğrula
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * IP Adresini Al
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // IP doğrulaması
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return 'UNKNOWN';
}

/**
 * User Agent'ı Al
 */
function getUserAgent() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'UNKNOWN';
}

/**
 * Email doğrula
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Güçlü şifre kontrolü
 * En az 8 karakter, büyük harf, küçük harf, sayı, özel karakter
 */
function isStrongPassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasLowerCase = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecialChar = preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password);
    
    return $hasUpperCase && $hasLowerCase && $hasNumber && $hasSpecialChar;
}

/**
 * Session kontrol - Kullanıcı oturum açmış mı?
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Oturum kapat
 */
function logout() {
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * HTML özel karakterleri encode et (XSS koruması)
 */
function escapeHTML($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Giriş denemeleri kontrol et (Brute Force Koruması)
 */
function checkLoginAttempts($pdo, $email, $userType) {
    $sql = $userType === 'user' 
        ? "SELECT login_attempts, locked_until FROM user_logins WHERE email = ?" 
        : "SELECT login_attempts, locked_until FROM member_logins WHERE email = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $result = $stmt->fetch();
    
    if ($result) {
        if ($result['locked_until'] && strtotime($result['locked_until']) > time()) {
            return [
                'locked' => true,
                'locked_until' => $result['locked_until']
            ];
        }
        
        if ($result['login_attempts'] >= 5) {
            return [
                'locked' => true,
                'message' => 'Çok fazla başarısız giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.'
            ];
        }
    }
    
    return ['locked' => false];
}

/**
 * Başarısız giriş denemesini kayıt et
 */
function recordFailedLogin($pdo, $email, $userType, $reason = 'Invalid credentials') {
    $table = $userType === 'user' ? 'user_logins' : 'member_logins';
    $sql = "UPDATE $table SET login_attempts = login_attempts + 1 WHERE email = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    
    // 5. denemeden sonra kilitle
    $sql = "SELECT login_attempts FROM $table WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $result = $stmt->fetch();
    
    if ($result && $result['login_attempts'] >= 5) {
        $lockedUntil = date('Y-m-d H:i:s', time() + 900); // 15 dakika
        $sql = "UPDATE $table SET locked_until = ? WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$lockedUntil, $email]);
    }
}

/**
 * Başarılı giriş denemesini sıfırla
 */
function resetLoginAttempts($pdo, $email, $userType) {
    $table = $userType === 'user' ? 'user_logins' : 'member_logins';
    $sql = "UPDATE $table SET login_attempts = 0, locked_until = NULL WHERE email = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
}

/**
 * Telefonun geçerli olup olmadığını kontrol et
 */
function validatePhone($phone) {
    // Türkiye telefon numaraları: +90 veya 0 ile başlar
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 12;
}

/**
 * Rate Limiting - İstek başına limitleme
 */
function checkRateLimit($identifier, $limit = 10, $window = 60) {
    $cacheKey = "rate_limit_" . md5($identifier);
    
    if (!isset($_SESSION[$cacheKey])) {
        $_SESSION[$cacheKey] = [];
    }
    
    $now = time();
    $_SESSION[$cacheKey] = array_filter(
        $_SESSION[$cacheKey],
        function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        }
    );
    
    if (count($_SESSION[$cacheKey]) >= $limit) {
        return false;
    }
    
    $_SESSION[$cacheKey][] = $now;
    return true;
}
