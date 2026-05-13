<?php
/**
 * controllers/UserController.php
 */
class UserController {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function index(): void {
        Auth::requireRole('admin');
        $stmt = $this->db->query(
            'SELECT id, username, email, full_name, role, created_at FROM users ORDER BY created_at DESC'
        );
        Response::json($stmt->fetchAll());
    }

    public function show(int $id): void {
        $user = Auth::require();
        if ($user['role'] !== 'admin' && (int)$user['id'] !== $id) Response::error('Forbidden.', 403);

        $stmt = $this->db->prepare(
            'SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Response::error('User not found.', 404);
        Response::json($row);
    }

    public function update(int $id, array $body): void {
        $user = Auth::require();
        if ($user['role'] !== 'admin' && (int)$user['id'] !== $id) Response::error('Forbidden.', 403);

        $sets   = [];
        $params = [];

        if (!empty($body['full_name'])) { $sets[] = 'full_name=?'; $params[] = trim($body['full_name']); }
        if (!empty($body['email']))     {
            if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) Response::error('Invalid email.', 422);
            $sets[] = 'email=?'; $params[] = strtolower(trim($body['email']));
        }
        if (!empty($body['password'])) {
            $v = new Validator(['password' => $body['password']]);
            $v->password('password'); $v->failFast();
            $sets[] = 'password=?'; $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
        }
        if (!$sets) Response::error('No fields to update.', 400);

        $params[] = $id;
        $this->db->prepare('UPDATE users SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
        Response::success('User updated successfully.');
    }

    public function delete(int $id): void {
        Auth::requireRole('admin');
        $stmt = $this->db->prepare('DELETE FROM users WHERE id=? AND role != "admin"');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) Response::error('User not found or admin accounts cannot be deleted.', 404);
        Response::success('User deleted.');
    }
}
