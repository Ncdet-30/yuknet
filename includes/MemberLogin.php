<?php
/**
 * Üye Giriş Sınıfı
 * Yuknet.com - Nakliyeler İlan Sitesi
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/security.php';

class MemberLogin {
    private $pdo;
    private $errors = [];
    private $success = [];

    public function __construct() {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    /**
     * Üye Kaydı
     */
    public function register($email, $password, $firstName, $lastName, $phone, $companyName = null, $address = null) {
        // Validasyon
        if (!$this->validateRegistration($email, $password, $firstName, $lastName, $phone)) {
            return false;
        }

        try {
            // Email zaten var mı?
            $stmt = $this->pdo->prepare("SELECT id FROM member_logins WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $this->errors[] = "Bu email zaten kay��tlı";
                return false;
            }

            // Şifreyi hash'le
            $passwordHash = hashPassword($password);

            // Doğrulama token'ı oluştur
            $verificationToken = bin2hex(random_bytes(32));

            // Veritabanına ekle (başlangıçta pending durumunda)
            $sql = "INSERT INTO member_logins 
                    (email, password_hash, first_name, last_name, phone, company_name, address, status, verification_token, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $email, 
                $passwordHash, 
                $firstName, 
                $lastName, 
                $phone, 
                $companyName,
                $address,
                $verificationToken,
                getClientIP()
            ]);

            $this->success[] = "Kayıt başarılı! Email'inizi doğrulamak için aktivasyon linkine tıklayın.";
            return [
                'success' => true,
                'verification_token' => $verificationToken,
                'email' => $email
            ];
        } catch (PDOException $e) {
            $this->errors[] = "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Email Doğrulama
     */
    public function verifyEmail($email, $token) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, verification_token FROM member_logins WHERE email = ?");
            $stmt->execute([$email]);
            $member = $stmt->fetch();

            if (!$member) {
                $this->errors[] = "Üye bulunamadı";
                return false;
            }

            if (!hash_equals($member['verification_token'], $token)) {
                $this->errors[] = "Doğrulama token'ı geçersiz";
                return false;
            }

            // Email'i doğrula
            $stmt = $this->pdo->prepare("UPDATE member_logins SET status = 'active', verified_at = ?, verification_token = NULL WHERE id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), $member['id']]);

            $this->success[] = "Email başarıyla doğrulandı! Artık giriş yapabilirsiniz.";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Doğrulama sırasında hata: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Üye Girişi
     */
    public function login($email, $password) {
        // Rate limiting kontrol
        if (!checkRateLimit($email, 5, 60)) {
            $this->errors[] = "Çok fazla giriş denemesi. Lütfen 1 dakika sonra tekrar deneyin.";
            return false;
        }

        // Brute force koruması
        $attemptCheck = checkLoginAttempts($this->pdo, $email, 'member');
        if ($attemptCheck['locked']) {
            $this->errors[] = $attemptCheck['message'] ?? "Hesap kilitli. Lütfen daha sonra deneyin.";
            return false;
        }

        try {
            // Email ile üyeyi bul
            $stmt = $this->pdo->prepare("SELECT * FROM member_logins WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $member = $stmt->fetch();

            if (!$member) {
                recordFailedLogin($this->pdo, $email, 'member', 'Member not found or not verified');
                $this->errors[] = "Email veya Şifre hatalı, ya da hesap henüz doğrulanmamış";
                return false;
            }

            // Şifreyi doğrula
            if (!verifyPassword($password, $member['password_hash'])) {
                recordFailedLogin($this->pdo, $email, 'member', 'Invalid password');
                $this->errors[] = "Email veya Şifre hatalı";
                return false;
            }

            // Başarılı giriş
            resetLoginAttempts($this->pdo, $email, 'member');
            
            // Session oluştur
            $this->createSession($member, 'member');
            
            // Son giriş zamanını güncelle
            $stmt = $this->pdo->prepare("UPDATE member_logins SET last_login = ?, ip_address = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), getClientIP(), $member['id']]);

            // Login geçmişine kayıt et
            $this->recordLoginHistory($member['id'], 'member', 'success');

            $this->success[] = "Hoş geldiniz, " . $member['first_name'] . "!";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Giriş sırasında bir hata oluştu: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Profil Bilgilerini Güncelle
     */
    public function updateProfile($memberId, $firstName, $lastName, $phone, $companyName, $address, $city, $province, $postalCode, $taxId) {
        // Telefon doğrulama
        if (!validatePhone($phone)) {
            $this->errors[] = "Geçerli bir telefon numarası girin";
            return false;
        }

        try {
            $sql = "UPDATE member_logins 
                    SET first_name = ?, last_name = ?, phone = ?, company_name = ?, 
                        address = ?, city = ?, province = ?, postal_code = ?, tax_id = ? 
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $firstName, $lastName, $phone, $companyName, 
                $address, $city, $province, $postalCode, $taxId, $memberId
            ]);

            $this->success[] = "Profil başarıyla güncellendi";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Güncelleme sırasında hata: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Şifre Değiştir
     */
    public function changePassword($memberId, $oldPassword, $newPassword, $confirmPassword) {
        // Validasyon
        if (strlen($newPassword) < 8) {
            $this->errors[] = "Yeni Şifre en az 8 karakter olmalı";
            return false;
        }

        if ($newPassword !== $confirmPassword) {
            $this->errors[] = "Şif reler eşleşmiyor";
            return false;
        }

        if (!isStrongPassword($newPassword)) {
            $this->errors[] = "Şifre en az bir büyük harf, bir küçük harf, bir sayı ve bir özel karakter içermelidir";
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT password_hash FROM member_logins WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch();

            if (!$member || !verifyPassword($oldPassword, $member['password_hash'])) {
                $this->errors[] = "Eski Şifre hatalı";
                return false;
            }

            $newHash = hashPassword($newPassword);
            $stmt = $this->pdo->prepare("UPDATE member_logins SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $memberId]);

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
    private function createSession($member, $userType) {
        $_SESSION['user_id'] = $member['id'];
        $_SESSION['first_name'] = $member['first_name'];
        $_SESSION['last_name'] = $member['last_name'];
        $_SESSION['email'] = $member['email'];
        $_SESSION['company_name'] = $member['company_name'];
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
            $stmt->execute([$sessionToken, $userType, $member['id'], getClientIP(), getUserAgent(), $expiresAt]);
        } catch (PDOException $e) {
            // Session'u yine de oluştur
        }
    }

    /**
     * Login Geçmişine Kayıt Et
     */
    private function recordLoginHistory($memberId, $userType, $status, $reason = null) {
        try {
            $sql = "INSERT INTO login_history (user_type, user_id, ip_address, user_agent, login_status, failure_reason) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userType, $memberId, getClientIP(), getUserAgent(), $status, $reason]);
        } catch (PDOException $e) {
            // Sessiz geç
        }
    }

    /**
     * Validasyon
     */
    private function validateRegistration($email, $password, $firstName, $lastName, $phone) {
        if (!validateEmail($email)) {
            $this->errors[] = "Geçerli bir email adresi girin";
            return false;
        }

        if (!isStrongPassword($password)) {
            $this->errors[] = "Şifre en az 8 karakter, 1 büyük harf, 1 küçük harf, 1 sayı ve 1 özel karakter içermelidir";
            return false;
        }

        if (strlen($firstName) < 2 || strlen($lastName) < 2) {
            $this->errors[] = "Ad ve soyad en az 2 karakter olmalı";
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
