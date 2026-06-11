<?php
/**
 * PDO Veritabanı Bağlantı Sınıfı
 * Yuknet.com - Nakliyeler İlan Sitesi
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'yuknet_db';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $pdo;
    private $error;

    /**
     * Veritabanına bağlan
     */
    public function connect() {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=' . $this->charset;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES  => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            return $this->pdo;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die('Veritabanı bağlantısı başarısız: ' . $this->error);
        }
    }

    /**
     * PDO nesnesini döndür
     */
    public function getPDO() {
        if (!$this->pdo) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Hata mesajını döndür
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Veritabanı konfigürasyonunu güncelle
     */
    public function setConfig($host, $db_name, $username, $password) {
        $this->host = $host;
        $this->db_name = $db_name;
        $this->username = $username;
        $this->password = $password;
    }
}
