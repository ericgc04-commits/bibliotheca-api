<?php
/**
 * controllers/CategoryController.php
 */

class CategoryController {

    private PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function index(): void {
        $stmt = $this->db->query("
            SELECT c.*, COUNT(b.id) AS book_count
            FROM categories c
            LEFT JOIN books b ON b.category_id = c.id
            GROUP BY c.id
            ORDER BY c.name");
        Response::json($stmt->fetchAll());
    }

    public function show(int $id): void {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Response::error('Category not found.', 404);
        Response::json($row);
    }

    public function create(array $body): void {
        Auth::requireRole('admin');
        $v = new Validator($body);
        $v->required('name');
        $v->failFast();
        try {
            $stmt = $this->db->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([trim($body['name']), $body['description'] ?? null]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Response::error('Category already exists.', 409);
            }
            throw $e;
        }
        $this->show((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $body): void {
        Auth::requireRole('admin');
        $v = new Validator($body);
        $v->required('name');
        $v->failFast();
        $stmt = $this->db->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
        $stmt->execute([trim($body['name']), $body['description'] ?? null, $id]);
        if ($stmt->rowCount() === 0) Response::error('Category not found.', 404);
        $this->show($id);
    }

    public function delete(int $id): void {
        Auth::requireRole('admin');
        $cnt = $this->db->prepare('SELECT COUNT(*) FROM books WHERE category_id = ?');
        $cnt->execute([$id]);
        if ((int) $cnt->fetchColumn() > 0) {
            Response::error('Cannot delete: category still has books.', 409);
        }
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) Response::error('Category not found.', 404);
        Response::success('Category deleted.');
    }
}
