<?php
/**
 * controllers/AuthController.php
 * Handles registration, login, and profile retrieval.
 */

class AuthController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * POST /api/auth/register
     */
    public function register(array $body): void {
        $v = new Validator($body);
        $v->required('username')->minLength('username', 3)->maxLength('username', 50)
          ->required('email')->email('email')
          ->required('password')->password('password')
          ->required('full_name', 'Full name');
        $v->failFast();

        $username  = trim($body['username']);
        $email     = strtolower(trim($body['email']));
        $password  = $body['password'];
        $full_name = trim($body['full_name']);

        // Duplicate check
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            Response::error('Username or email is already in use.', 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash, $full_name, 'member']);
        $id = (int) $this->db->lastInsertId();

        $token = JWT::encode(['id' => $id, 'username' => $username, 'role' => 'member']);

        Response::json([
            'data'  => ['id' => $id, 'username' => $username, 'email' => $email, 'full_name' => $full_name, 'role' => 'member'],
            'token' => $token,
        ], 201, 'Registration successful.');
    }

    /**
     * POST /api/auth/login
     */
    public function login(array $body): void {
        $v = new Validator($body);
        $v->required('username')->required('password');
        $v->failFast();

        $stmt = $this->db->prepare(
            'SELECT id, username, email, password, full_name, role FROM users WHERE username = ?'
        );
        $stmt->execute([trim($body['username'])]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($body['password'], $user['password'])) {
            Response::error('Invalid username or password.', 401);
        }

        $token = JWT::encode(['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]);

        unset($user['password']);
        Response::json(['data' => $user, 'token' => $token], 200, 'Login successful.');
    }

    /**
     * GET /api/auth/me  (protected)
     */
    public function me(): void {
        $user = Auth::require();

        $stmt = $this->db->prepare(
            'SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row) Response::error('User not found.', 404);
        Response::json($row);
    }
}
