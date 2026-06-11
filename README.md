# Yuknet.com - Nakliyeler İlan Sitesi

Güvenli PHP PDO tabanlı kullanıcı ve üye giriş sistemi.

## 🔐 Özellikler

### Güvenlik
- ✅ **Şifre Hashing**: BCRYPT algoritması (cost: 12)
- ✅ **CSRF Koruması**: Token-based CSRF prevention
- ✅ **Brute Force Koruması**: Başarısız giriş denemeleri sonrası hesap kilitleme
- ✅ **Rate Limiting**: İstek başına sınırlama
- ✅ **SQL Injection Koruması**: Prepared Statements (PDO)
- ✅ **XSS Koruması**: HTML özel karakterleri encode etme
- ✅ **Güçlü Şifre Gereksimleri**: Minimum 8 karakter, büyük/küçük harf, sayı, özel karakter
- ✅ **Session Management**: Veritabanı tabanlı session yönetimi
- ✅ **Login Geçmişi**: Tüm giriş denemeleri kaydedilir

### Özellikler
- 📝 **Ayrı Tablolar**: Kullanıcı (user_logins) ve Üye (member_logins) için
- 📧 **Email Doğrulama**: Üye kaydında email doğrulama token'ı
- 🔄 **Şifre Sıfırlama**: Secure token-based password reset
- 👥 **Profil Yönetimi**: Kullanıcı profili güncelleme
- 📱 **Telefon Doğrulama**: Telefon numarası format kontrolü
- 🔒 **Session Timeout**: Otomatik session süresi doldurma

## 📁 Proje Yapısı

```
yuknet/
├── config/
│   ├── Database.php          # PDO veritabanı bağlantısı
│   └── security.php          # Güvenlik fonksiyonları
├── includes/
│   ├── UserLogin.php         # Kullanıcı giriş sınıfı
│   ├── MemberLogin.php       # Üye giriş sınıfı
│   └── AuthMiddleware.php    # Oturum kontrol middleware
├── pages/
│   ├── user-login.php        # Kullanıcı giriş/kayıt formu
│   └── member-login.php      # Üye giriş/kayıt/doğrulama formu
├── database.sql              # Veritabanı yapısı
└── README.md                 # Bu dosya
```

## 🚀 Kurulum

### 1. Veritabanı Oluştur
```sql
CREATE DATABASE yuknet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Tabloları Oluştur
`database.sql` dosyasının içeriğini çalıştır:
```bash
mysql -u root yuknet_db < database.sql
```

### 3. PDO Bağlantısını Ayarla
`config/Database.php` dosyasında veritabanı bilgilerini güncelle:
```php
private $host = 'localhost';
private $db_name = 'yuknet_db';
private $username = 'root';
private $password = '';
```

### 4. Klasörler Oluştur
```bash
mkdir -p config includes pages uploads
```

## 📋 Kullanıcı Giriş Akışı

### Şimdi Giriş (User)
1. `/pages/user-login.php` adresine git
2. "Giriş Yap" sekmesinde email ve şifre gir
3. Başarılı girişte dashboard'a yönlendir

### Kullanıcı Kayıt (User)
1. "Kayıt Ol" sekmesine git
2. Kullanıcı adı, email, şifre, telefon gir
3. Şifre 8+ karakter, 1 büyük, 1 küçük, 1 sayı, 1 özel karakter içermeli
4. Kayıt başarılı, giriş yapabilir

### Üye Kaydı (Member)
1. `/pages/member-login.php` adresine git
2. "Üye Ol" sekmesine git
3. Kişisel ve şirket bilgilerini gir
4. Email doğrulama linki gönderilir
5. Doğrulama token'ını gir
6. Aktivasyon tamamlandı, giriş yapabilir

### Üye Girişi (Member)
1. Email ve şifre ile giriş yap
2. Başarılı girişte member dashboard'a yönlendir

## 🔑 Veritabanı Tabloları

### user_logins
```
- id: INT (Primary Key)
- username: VARCHAR(50) UNIQUE
- email: VARCHAR(100) UNIQUE
- password_hash: VARCHAR(255)
- phone: VARCHAR(20)
- status: ENUM(active, inactive, suspended)
- last_login: DATETIME
- created_at: DATETIME
- updated_at: DATETIME
- ip_address: VARCHAR(45)
- login_attempts: INT
- locked_until: DATETIME
```

### member_logins
```
- id: INT (Primary Key)
- email: VARCHAR(100) UNIQUE
- password_hash: VARCHAR(255)
- first_name: VARCHAR(100)
- last_name: VARCHAR(100)
- company_name: VARCHAR(150)
- phone: VARCHAR(20)
- phone_verified: TINYINT
- address: TEXT
- city: VARCHAR(50)
- province: VARCHAR(50)
- postal_code: VARCHAR(10)
- tax_id: VARCHAR(20)
- status: ENUM(pending, active, inactive, suspended)
- verification_token: VARCHAR(255)
- verified_at: DATETIME
- last_login: DATETIME
- created_at: DATETIME
- updated_at: DATETIME
- ip_address: VARCHAR(45)
- login_attempts: INT
- locked_until: DATETIME
```

### login_history
```
- id: INT (Primary Key)
- user_type: ENUM(user, member)
- user_id: INT
- ip_address: VARCHAR(45)
- user_agent: TEXT
- login_status: ENUM(success, failed)
- failure_reason: VARCHAR(100)
- login_time: DATETIME
```

### sessions
```
- session_id: VARCHAR(255) (Primary Key)
- user_type: ENUM(user, member)
- user_id: INT
- ip_address: VARCHAR(45)
- user_agent: TEXT
- created_at: DATETIME
- last_activity: DATETIME
- expires_at: DATETIME
```

## 🛡️ Güvenlik Özellikleri

### Brute Force Koruması
- Başarısız 5 giriş denemesinden sonra hesap 15 dakika kilitlenir
- `checkLoginAttempts()` fonksiyonu ile kontrol edilir
- `recordFailedLogin()` ile başarısız denemeleri kayıt eder

### Rate Limiting
- Dakikada maksimum 5 giriş denemesi
- `checkRateLimit()` fonksiyonu ile kontrol edilir

### Şifre Güvenliği
- BCRYPT hashing (cost: 12) ile şifreler hash'lenir
- Güçlü şifre gereksinimleri uygulanır
- `isStrongPassword()` fonksiyonu ile doğrulama yapılır

### CSRF Koruması
- Tüm formlar CSRF token ile korunur
- `getCsrfTokenInput()` ve `verifyCsrfToken()` fonksiyonları kullanılır

### Session Yönetimi
- Session'lar veritabanında saklanır
- 7 gün expiration time
- Otomatik timeout kontrol

## 📝 Örnek Kullanım

### UserLogin Sınıfı
```php
require_once 'includes/UserLogin.php';

$userLogin = new UserLogin();

// Kayıt
$userLogin->register('username', 'email@example.com', 'Password123!', '05051234567');

// Giriş
if ($userLogin->login('email@example.com', 'Password123!')) {
    echo "Giriş başarılı!";
} else {
    $errors = $userLogin->getErrors();
}

// Şifre Değiştir
$userLogin->changePassword($userId, $oldPassword, $newPassword, $confirmPassword);
```

### MemberLogin Sınıfı
```php
require_once 'includes/MemberLogin.php';

$memberLogin = new MemberLogin();

// Kayıt
$result = $memberLogin->register(
    'email@example.com',
    'Password123!',
    'Ad',
    'Soyad',
    '05051234567',
    'Şirket Adı',
    'Adres'
);

// Email Doğrulama
$memberLogin->verifyEmail('email@example.com', $token);

// Giriş
if ($memberLogin->login('email@example.com', 'Password123!')) {
    echo "Giriş başarılı!";
}

// Profil Güncelle
$memberLogin->updateProfile($memberId, 'Ad', 'Soyad', '05051234567', ...);
```

## 🔗 Endpoints

- **Kullanıcı Giriş**: `/pages/user-login.php`
- **Üye Giriş**: `/pages/member-login.php`

## ⚙️ Konfigürasyon

Tüm güvenlik ayarları `config/security.php` dosyasında:
- Session timeout
- Password requirements
- CSRF token ayarları
- Rate limiting ayarları

## 📚 API Fonksiyonları

### security.php
- `hashPassword($password)` - Şifre hash'le
- `verifyPassword($password, $hash)` - Şifre doğrula
- `getClientIP()` - Client IP'si al
- `validateEmail($email)` - Email doğrula
- `isStrongPassword($password)` - Güçlü şifre kontrolü
- `validatePhone($phone)` - Telefon doğrula
- `checkLoginAttempts($pdo, $email, $userType)` - Giriş denemeleri kontrol
- `recordFailedLogin($pdo, $email, $userType, $reason)` - Başarısız giriş kayıt
- `resetLoginAttempts($pdo, $email, $userType)` - Giriş denemelerini sıfırla
- `checkRateLimit($identifier, $limit, $window)` - Rate limiting kontrolü

## 🐛 Hata Yönetimi

Tüm sınıflar `getErrors()` ve `getSuccess()` metotları ile hata/başarı mesajları döndürür:

```php
if (!$userLogin->register(...)) {
    $errors = $userLogin->getErrors();
    foreach ($errors as $error) {
        echo $error . "\n";
    }
}
```

## 📞 İletişim

Yuknet.com - Nakliyeler İlan Sitesi

---

**Geliştirici Notları:**
- Veritabanı bağlantı bilgilerini `.env` dosyasında saklamanız önerilir
- Email gönderme işlevi için PHPMailer veya benzer bir kütüphane ekleyebilirsiniz
- İngiltere versiyonu için dil dosyaları (i18n) ekleyebilirsiniz
- Admin paneli için yönetim arayüzü geliştirilmesi önerilir
