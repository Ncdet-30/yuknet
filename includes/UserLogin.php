<?php
/**
 * Kullanıcı Giriş Sınıfı
 * Yuknet.com - Nakliyeler İlan Sitesi
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/security.php';

class UserLogin {
    private $pdo;
    private $errors = [];
    private $success = [];

    public function __construct() {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    /**
     * Kullanıcı Kaydı
     */
    public function register($username, $email, $password, $phone) {
        // Validasyon
        if (!$this->validateRegistration($username, $email, $password, $phone)) {
            return false;
        }

        try {
            // Email zaten var mı?
            $stmt = $this->pdo->prepare("SELECT id FROM user_logins WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            
            if ($stmt->rowCount() > 0) {
                $this->errors[] = "Bu email veya kullanıcı adı zaten kayıtlı";
                return false;
            }

            // Şifreyi hash'le
            $passwordHash = hashPassword($password);

            // Veritabanına ekle
            $sql = "INSERT INTO user_logins (username, email, password_hash, phone, status, ip_address) 
                    VALUES (?, ?, ?, ?, 'active', ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$username, $email, $passwordHash, $phone, getClientIP()]);

            $this->success[] = "Kayıt başarılı! Giriş yapabilirsiniz.";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Kullanıcı Girişi
     */
    public function login($email, $password) {
        // Rate limiting kontrol
        if (!checkRateLimit($email, 5, 60)) {
            $this->errors[] = "Çok fazla giriş denemesi. Lütfen 1 dakika sonra tekrar deneyin.";
            return false;
        }

        // Brute force koruması
        $attemptCheck = checkLoginAttempts($this->pdo, $email, 'user');
        if ($attemptCheck['locked']) {
            $this->errors[] = $attemptCheck['message'] ?? "Hesap kilitli. Lütfen daha sonra deneyin.";
            return false;
        }

        try {
            // Email ile kullanıcıyı bul
            $stmt = $this->pdo->prepare("SELECT * FROM user_logins WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                recordFailedLogin($this->pdo, $email, 'user', 'User not found');
                $this->errors[] = "Email veya Şifre hatalı";
                return false;
            }

            // Şifreyi doğrula
            if (!verifyPassword($password, $user['password_hash'])) {
                recordFailedLogin($this->pdo, $email, 'user', 'Invalid password');
                $this->errors[] = "Email veya Şifre hatalı";
                return false;
            }

            // Başarılı giriş
            resetLoginAttempts($this->pdo, $email, 'user');
            
            // Session oluştur
            $this->createSession($user, 'user');
            
            // Son giriş zamanını güncelle
            $stmt = $this->pdo->prepare("UPDATE user_logins SET last_login = ?, ip_address = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), getClientIP(), $user['id']]);

            // Login geçmişine kayıt et
            $this->recordLoginHistory($user['id'], 'user', 'success');

            $this->success[] = "Hoş geldiniz!";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Giriş sırasında bir hata oluştu: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Şifre Sıfırlama - Token Oluştur
     */
    public function generatePasswordResetToken($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM user_logins WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->errors[] = "Bu email ile kayıtlı hesap bulunamadı";
                return false;
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 saat geçerli

            // Token'ı yeni bir tabloya kaydet (veya user_logins'e field ekle)
            // Şu an için basit bir çözüm:
            $_SESSION['reset_token_' . $email] = [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user_id' => $user['id']
            ];

            $this->success[] = "Şifre sıfırlama linki email'inize gönderildi.";
            return $token;
        } catch (PDOException $e) {
            $this->errors[] = "Hata: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Şifre Değiştir
     */
    public function changePassword($userId, $oldPassword, $newPassword, $confirmPassword) {
        // Validasyon
        if (strlen($newPassword) < 8) {
            $this->errors[] = "Yeni Şifre en az 8 karakter olmalı";
            return false;
        }

        if ($newPassword !== $confirmPassword) {
            $this->errors[] = "Şifreler eşleşmiyor";
            return false;
        }

        if (!isStrongPassword($newPassword)) {
            $this->errors[] = "Şifre en az bir büyük harf, bir küçük harf, bir sayı ve bir özel karakter içermelidir";
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT password_hash FROM user_logins WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !verifyPassword($oldPassword, $user['password_hash'])) {
                $this->errors[] = "Eski Şifre hatalı";
                return false;
            }

            $newHash = hashPassword($newPassword);
            $stmt = $this->pdo->prepare("UPDATE user_logins SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $userId]);

            $this->success[] = "Şifre başarıyla değiştirildi";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Hata: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Session Oluştur
     */
    private function createSession($user, $userType) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $userType;
        $_SESSION['login_time'] = time();
        
        // Session token oluştur
        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $sessionToken;

        // Session'u veritabanına kaydet
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 7); // 7 gün
            $sql = "INSERT INTO sessions (session_id, user_type, user_id, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$sessionToken, $userType, $user['id'], getClientIP(), getUserAgent(), $expiresAt]);
        } catch (PDOException $e) {
            // Session'u yine de oluştur
        }
    }

    /**
     * Login Geçmişine Kayıt Et
     */
    private function recordLoginHistory($userId, $userType, $status, $reason = null) {
        try {
            $sql = "INSERT INTO login_history (user_type, user_id, ip_address, user_agent, login_status, failure_reason) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userType, $userId, getClientIP(), getUserAgent(), $status, $reason]);
        } catch (PDOException $e) {
            // Sessiz geç
        }
    }

    /**
     * Validasyon
     */
    private function validateRegistration($username, $email, $password, $phone) {
        if (strlen($username) < 3) {
            $this->errors[] = "Kullanıcı adı en az 3 karakter olmalı";
            return false;
        }

        if (!validateEmail($email)) {
            $this->errors[] = "Geçerli bir email adresi girin";
            return false;
        }

        if (!isStrongPassword($password)) {
            $this->errors[] = "Şifre en az 8 karakter, 1 büyük harf, 1 küçük harf, 1 sayı ve 1 özel karakter içermelidir";
            return false;
        }

        if (!validatePhone($phone)) {
            $this->errors[] = "Geçerli bir telefon numarası girin";
            return false;
        }

        return true;
    }

    /**
     * Hata Mesajlarını Al
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Başarı Mesajlarını Al
     */
    public function getSuccess() {
        return $this->success;
    }
}
