<?php
/**
 * Gestion centralisée de la base de données pour The Mind
 */

// Empêcher l'accès direct
if (!defined('CONFIG_LOADED')) {
    die('Accès direct interdit');
}

/**
 * Classe de gestion de la base de données
 */
class Database {
    private static $instance = null;
    private $pdo = null;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Obtenir l'instance unique de la base de données
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Établir la connexion à la base de données
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            die("Erreur de connexion à la base de données");
        }
    }
    
    /**
     * Obtenir l'objet PDO
     */
    public function getPDO() {
        return $this->pdo;
    }
    
    /**
     * Exécuter une requête préparée
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur de requête SQL: " . $e->getMessage() . " - SQL: " . $sql);
            throw $e;
        }
    }
    
    /**
     * Récupérer un seul enregistrement
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Récupérer tous les enregistrements
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer une seule colonne
     */
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Insérer un enregistrement
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Mettre à jour un enregistrement
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    /**
     * Supprimer un enregistrement
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
    
    /**
     * Compter les enregistrements
     */
    public function count($table, $where = '1=1', $params = []) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return $this->fetchColumn($sql, $params);
    }
    
    /**
     * Vérifier si un enregistrement existe
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }
    
    /**
     * Commencer une transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Valider une transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Annuler une transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Vérifier si une transaction est active
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}

/**
 * Classe pour les requêtes spécifiques au jeu The Mind
 */
class GameDatabase extends Database {
    
    /**
     * Récupérer les informations d'un utilisateur
     */
    public function getUser($identifier, $byId = false) {
        $field = $byId ? 'id' : 'identifiant';
        $sql = "SELECT u.*, r.nom as role_nom 
                FROM Utilisateurs u 
                JOIN Roles r ON u.id_role = r.id 
                WHERE u.{$field} = :identifier";
        return $this->fetch($sql, ['identifier' => $identifier]);
    }
    
    /**
     * Récupérer les parties disponibles
     */
    public function getAvailableGames($userId = null) {
        $sql = "SELECT p.id, p.niveau, p.status, p.nombre_joueurs, p.nom,
                       (SELECT COUNT(*) FROM Utilisateurs_Parties WHERE id_partie = p.id) as joueurs_actuels,
                       (SELECT identifiant FROM Utilisateurs WHERE id = 
                            (SELECT id_utilisateur FROM Utilisateurs_Parties WHERE id_partie = p.id AND position = 1 LIMIT 1)
                       ) as admin_nom";
        
        if ($userId) {
            $sql .= ", (SELECT COUNT(*) FROM Utilisateurs_Parties WHERE id_partie = p.id AND id_utilisateur = :user_id) as user_joined";
        }
        
        $sql .= " FROM Parties p ORDER BY p.date_creation DESC";
        
        $params = $userId ? ['user_id' => $userId] : [];
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Récupérer les informations d'une partie
     */
    public function getGameInfo($gameId) {
        $sql = "SELECT p.*, 
                       (SELECT COUNT(*) FROM Utilisateurs_Parties WHERE id_partie = p.id) as joueurs_actuels
                FROM Parties p 
                WHERE p.id = :game_id";
        return $this->fetch($sql, ['game_id' => $gameId]);
    }
    
    /**
     * Récupérer les joueurs d'une partie
     */
    public function getGamePlayers($gameId) {
        $sql = "SELECT u.id, u.identifiant, u.avatar, up.position,
                       (SELECT COUNT(*) FROM Cartes WHERE id_utilisateur = u.id AND id_partie = :game_id AND etat = 'en_main') as cartes_en_main
                FROM Utilisateurs u
                JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur
                WHERE up.id_partie = :game_id
                ORDER BY up.position";
        return $this->fetchAll($sql, ['game_id' => $gameId]);
    }
    
    /**
     * Récupérer les cartes d'un joueur
     */
    public function getPlayerCards($gameId, $userId) {
        $sql = "SELECT id, valeur 
                FROM Cartes 
                WHERE id_partie = :game_id AND id_utilisateur = :user_id AND etat = 'en_main'
                ORDER BY valeur";
        return $this->fetchAll($sql, ['game_id' => $gameId, 'user_id' => $userId]);
    }
    
    /**
     * Récupérer les cartes jouées
     */
    public function getPlayedCards($gameId, $limit = 10) {
        $sql = "SELECT c.id, c.valeur, u.identifiant as joueur_nom 
                FROM Cartes c 
                JOIN Utilisateurs u ON c.id_utilisateur = u.id
                WHERE c.id_partie = :game_id AND c.etat = 'jouee'
                ORDER BY c.date_action DESC
                LIMIT :limit";
        return $this->fetchAll($sql, ['game_id' => $gameId, 'limit' => $limit]);
    }
    
    /**
     * Récupérer les statistiques d'un utilisateur
     */
    public function getUserStats($userId) {
        $sql = "SELECT parties_jouees, parties_gagnees, taux_reussite, cartes_jouees 
                FROM Statistiques 
                WHERE id_utilisateur = :user_id";
        $stats = $this->fetch($sql, ['user_id' => $userId]);
        
        if (!$stats) {
            // Créer des statistiques par défaut si elles n'existent pas
            $this->insert('Statistiques', [
                'id_utilisateur' => $userId,
                'parties_jouees' => 0,
                'parties_gagnees' => 0,
                'taux_reussite' => 0,
                'cartes_jouees' => 0
            ]);
            return [
                'parties_jouees' => 0,
                'parties_gagnees' => 0,
                'taux_reussite' => 0,
                'cartes_jouees' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Récupérer le niveau maximum atteint par un utilisateur
     */
    public function getUserMaxLevel($userId) {
        $sql = "SELECT MAX(niveau_max_atteint) as niveau_max 
                FROM Scores 
                WHERE id_utilisateur = :user_id";
        return $this->fetchColumn($sql, ['user_id' => $userId]) ?: 0;
    }
    
    /**
     * Vérifier si un utilisateur participe à une partie
     */
    public function isUserInGame($userId, $gameId) {
        return $this->exists('Utilisateurs_parties', 'id_utilisateur = :user_id AND id_partie = :game_id', [
            'user_id' => $userId,
            'game_id' => $gameId
        ]);
    }
    
    /**
     * Vérifier si un utilisateur est admin d'une partie
     */
    public function isUserGameAdmin($userId, $gameId) {
        return $this->exists('Utilisateurs_parties', 
            'id_utilisateur = :user_id AND id_partie = :game_id AND position = 1', [
            'user_id' => $userId,
            'game_id' => $gameId
        ]);
    }
    
    /**
     * Distribuer les cartes aux joueurs d'une partie
     */
    public function distributeCards($gameId) {
        // Récupérer les informations de la partie
        $gameInfo = $this->getGameInfo($gameId);
        if (!$gameInfo) {
            throw new Exception("Partie non trouvée");
        }
        
        $niveau = $gameInfo['niveau'];
        $cardsPerPlayer = $niveau; // Le nombre de cartes par joueur est égal au niveau
        
        // Récupérer les joueurs de la partie
        $players = $this->getGamePlayers($gameId);
        
        // Générer un deck de cartes (1-100)
        $deck = range(1, 100);
        shuffle($deck);
        
        // Supprimer les anciennes cartes
        $this->delete('Cartes', 'id_partie = :game_id', ['game_id' => $gameId]);
        
        // Distribuer les cartes aux joueurs
        foreach ($players as $player) {
            $userId = $player['id'];
            
            for ($i = 0; $i < $cardsPerPlayer; $i++) {
                if (empty($deck)) break; // Vérifier qu'il reste des cartes
                
                $cardValue = array_pop($deck);
                
                $this->insert('Cartes', [
                    'id_partie' => $gameId,
                    'id_utilisateur' => $userId,
                    'valeur' => $cardValue,
                    'etat' => 'en_main'
                ]);
            }
        }
    }
    
    /**
     * Créer une nouvelle partie
     */
    public function createGame($creatorId, $gameData) {
        $this->beginTransaction();
        
        try {
            // Créer la partie
            $gameId = $this->insert('Parties', [
                'nom' => $gameData['nom'],
                'niveau' => $gameData['niveau'],
                'nombre_joueurs' => $gameData['nombre_joueurs'],
                'vies_restantes' => $gameData['vies'],
                'shurikens_restants' => $gameData['shurikens'],
                'difficulte' => $gameData['difficulte'],
                'status' => 'en_attente',
                'prive' => $gameData['prive'] ? 1 : 0,
                'date_creation' => date('Y-m-d H:i:s')
            ]);
            
            // Ajouter le créateur comme premier joueur (administrateur)
            $this->insert('Utilisateurs_parties', [
                'id_utilisateur' => $creatorId,
                'id_partie' => $gameId,
                'position' => 1
            ]);
            
            $this->commit();
            return $gameId;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Rejoindre une partie
     */
    public function joinGame($userId, $gameId) {
        $this->beginTransaction();
        
        try {
            // Vérifier si l'utilisateur fait déjà partie de la partie
            if ($this->isUserInGame($userId, $gameId)) {
                throw new Exception("Vous participez déjà à cette partie");
            }
            
            // Vérifier si la partie est disponible
            $game = $this->getGameInfo($gameId);
            if (!$game || $game['status'] !== 'en_attente') {
                throw new Exception("Cette partie n'est plus disponible");
            }
            
            if ($game['joueurs_actuels'] >= $game['nombre_joueurs']) {
                throw new Exception("Cette partie est complète");
            }
            
            // Trouver la position suivante
            $maxPosition = $this->fetchColumn(
                "SELECT MAX(position) FROM Utilisateurs_parties WHERE id_partie = :game_id",
                ['game_id' => $gameId]
            ) ?: 0;
            
            // Ajouter l'utilisateur à la partie
            $this->insert('Utilisateurs_parties', [
                'id_utilisateur' => $userId,
                'id_partie' => $gameId,
                'position' => $maxPosition + 1
            ]);
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}

// ===== FONCTIONS UTILITAIRES GLOBALES =====

/**
 * Obtenir l'instance de la base de données standard
 */
function db() {
    return Database::getInstance();
}

/**
 * Obtenir l'instance de la base de données de jeu
 */
function gameDb() {
    static $instance = null;
    if ($instance === null) {
        $instance = new GameDatabase();
    }
    return $instance;
}

/**
 * Fonction raccourcie pour une requête simple
 */
function dbQuery($sql, $params = []) {
    return db()->query($sql, $params);
}

/**
 * Fonction raccourcie pour récupérer un enregistrement
 */
function dbFetch($sql, $params = []) {
    return db()->fetch($sql, $params);
}

/**
 * Fonction raccourcie pour récupérer tous les enregistrements
 */
function dbFetchAll($sql, $params = []) {
    return db()->fetchAll($sql, $params);
}

/**
 * Fonction raccourcie pour insérer
 */
function dbInsert($table, $data) {
    return db()->insert($table, $data);
}

/**
 * Fonction raccourcie pour mettre à jour
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    return db()->update($table, $data, $where, $whereParams);
}

/**
 * Fonction raccourcie pour supprimer
 */
function dbDelete($table, $where, $params = []) {
    return db()->delete($table, $where, $params);
}
?>