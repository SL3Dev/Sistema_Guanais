<?php
/**
 * config.php
 * Configuração e conexão com o banco de dados
 * Espaço Guanais - Sistema de Gestão Clínica
 */

// Configurações de erro (em produção, mantenha display_errors = 0)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'espaco_guanais');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações da API
define('API_VERSION', '1.0.0');
define('API_NAME', 'Espaço Guanais API');

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Tratar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Headers de conteúdo
header('Content-Type: application/json; charset=utf-8');

/**
 * Classe de conexão com o banco de dados (Singleton)
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro de conexão com o banco de dados',
                'message' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Contate o administrador'
            ]);
            exit();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Funções auxiliares
 */

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function errorResponse($message, $statusCode = 400, $details = []) {
    $response = [
        'success' => false,
        'error' => $message
    ];
    if (!empty($details)) {
        $response['details'] = $details;
    }
    jsonResponse($response, $statusCode);
}

function successResponse($data, $message = 'Operação realizada com sucesso', $statusCode = 200) {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], $statusCode);
}

function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?? [];
}

function validateField($data, $field, $fieldName) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        return "O campo $fieldName é obrigatório";
    }
    return null;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function formatDateToISO($date) {
    if (empty($date)) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
        list($day, $month, $year) = explode('/', $date);
        return "$year-$month-$day";
    }
    return $date;
}

function formatDateToBR($date) {
    if (empty($date)) return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        list($year, $month, $day) = explode('-', $date);
        return "$day/$month/$year";
    }
    return $date;
}

function generateId($prefix = '') {
    return $prefix . uniqid() . substr(md5(uniqid()), 0, 4);
}

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isAuthenticated() {
    startSession();
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function requireAuth() {
    if (!isAuthenticated()) {
        errorResponse('Usuário não autenticado', 401);
    }
}

function hasPermission($modulo, $acao) {
    startSession();
    
    // Admin tem todas as permissões
    if (isset($_SESSION['usuario']) && $_SESSION['usuario'] === 'admin') {
        return true;
    }
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        return false;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT permitido FROM permissoes WHERE usuario_id = ? AND modulo = ? AND acao = ?");
    $stmt->execute([$_SESSION['user_id'], $modulo, $acao]);
    $result = $stmt->fetch();
    
    return $result && $result['permitido'] == 1;
}

function requirePermission($modulo, $acao) {
    if (!hasPermission($modulo, $acao)) {
        errorResponse('Permissão negada', 403);
    }
}

// Handler global de exceções para garantir JSON sempre
set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
    exit;
});