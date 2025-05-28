<?php
/**
 * Configuration de la base de données
 * Gestion de la connexion PDO avec singleton
 */
class Database {
    private static $instance = null;
    private $conn;
    
    // Paramètres de connexion
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'bddthemind';
    private const DB_USER = 'user';
    private const DB_PASS = 'Eloi2023*';
    
    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->conn = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            
        } catch(PDOException $e) {
            error_log("Erreur de connexion PDO: " . $e->getMessage());
            die("Une erreur est survenue lors de la connexion à la base de données. Veuillez réessayer plus tard.");
        }
    }
    
    /**
     * Récupère l'instance unique de la classe (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Retourne la connexion PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Empêche le clonage de l'instance
     */
    private function __clone() {}
    
    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

// Variable globale pour la rétrocompatibilité avec l'ancien code
$conn = Database::getInstance()->getConnection();
?>