<?php
/**
 * Authentication and Authorization Functions
 */

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id, username, email, password_hash, role, first_name, last_name FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                // Log login
                require_once __DIR__ . '/functions.php';
                logUserActivity('LOGIN', 'User logged in successfully', $user['id']);
                
                $stmt->close();
                return true;
            }
        }
        
        $stmt->close();
        return false;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Log logout before destroying session
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/functions.php';
            logUserActivity('LOGOUT', 'User logged out', $_SESSION['user_id']);
        }
        
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Require login - redirect if not logged in
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit();
        }
    }
    
    /**
     * Require specific role - redirect if not authorized
     */
    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            header('Location: ' . APP_URL . '/index.php');
            exit();
        }
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Get current user role
     */
    public function getRole() {
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }
    
    /**
     * Get current user data
     */
    public function getUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        // Get profile picture from database (if column exists)
        $profile_picture = null;
        $conn = $this->db->getConnection();
        
        // Check if profile_picture column exists
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
        if ($check_column && $check_column->num_rows > 0) {
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $profile_picture = $row['profile_picture'] ?? null;
            }
            $stmt->close();
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'profile_picture' => $profile_picture
        ];
    }
    
    /**
     * Create new user account
     */
    public function createUser($username, $email, $password, $role, $first_name, $last_name) {
        $conn = $this->db->getConnection();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $password_hash, $role, $first_name, $last_name);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            $stmt->close();
            return $user_id;
        }
        
        $stmt->close();
        return false;
    }
}

