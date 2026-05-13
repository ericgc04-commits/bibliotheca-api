<?php
/**
 * controllers/BookController.php
 * Full CRUD for books with search, filtering, pagination, and JOIN queries.
 */

class BookController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/books
     * Supports: search, category_id, available, sort, page, limit
     */
    public function index(): void {
        $search      = trim($_GET['search']      ?? '');
        $category_id = (int) ($_GET['category_id'] ?? 0);
        $available   = ($_GET['available'] ?? '') === 'true';
        $page        = max(1,  (int) ($_GET['page']  ?? 1));
        $limit       = min(50, max(1, (int) ($_GET['limit'] ?? 12)));
        $offset      = ($page - 1) * $limit;

        $allowed = ['title', 'author', 'published_year', 'available_copies'];
        $sort    = in_array($_GET['sort'] ?? '', $allowed, true) ? $_GET['sort'] : 'title';

        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)';
            $like     = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($category_id > 0) {
            $where   .= ' AND b.category_id = ?';
            $params[] = $category_id;
        }
        if ($available) {
            $where .= ' AND b.available_copies > 0';
        }

        // Count query
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM books b $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Data query — complex multi-table JOIN with aggregated rating
        $sql = "
            SELECT b.id, b.title, b.author, b.isbn, b.published_year, b.publisher,
                   b.total_copies, b.available_copies, b.description, b.cover_url,
                   c.id AS category_id, c.name AS category_name,
                   ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating,
                   COUNT(DISTINCT r.id)               AS review_count
            FROM books b
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN reviews r ON r.book_id = b.id
            $where
            GROUP BY b.id
            ORDER BY b.$sort
            LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
        $books = $stmt->fetchAll();

        Response::json([
            'data'       => $books,
            'pagination' => [
                'total'      => $total,
                'page'       => $page,
                'limit'      => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    /**
     * GET /api/books/{id}
     */
    public function show(int $id): void {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS category_name,
                   ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating,
                   COUNT(DISTINCT r.id)               AS review_count
            FROM books b
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN reviews r ON r.book_id = b.id
            WHERE b.id = ?
            GROUP BY b.id");
        $stmt->execute([$id]);
        $book = $stmt->fetch();

        if (!$book) Response::error('Book not found.', 404);

        // Recent reviews
        $rStmt = $this->db->prepare("
            SELECT r.id, r.rating, r.comment, r.created_at, u.username, u.full_name
            FROM reviews r
            JOIN users u ON u.id = r.user_id
            WHERE r.book_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5");
        $rStmt->execute([$id]);
        $book['reviews'] = $rStmt->fetchAll();

        Response::json($book);
    }

    /**
     * POST /api/books  (admin)
     */
    public function create(array $body): void {
        Auth::requireRole('admin');

        $v = new Validator($body);
        $v->required('title')->required('author')
          ->required('category_id')->integer('category_id', 1)
          ->integer('total_copies', 1, 999, 'Total copies');
        $v->failFast();

        $copies = max(1, (int) ($body['total_copies'] ?? 1));

        $stmt = $this->db->prepare("
            INSERT INTO books
              (title, author, isbn, category_id, description,
               total_copies, available_copies, published_year, publisher, cover_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([
                trim($body['title']),
                trim($body['author']),
                !empty($body['isbn'])        ? trim($body['isbn'])        : null,
                (int) $body['category_id'],
                !empty($body['description']) ? trim($body['description']) : null,
                $copies, $copies,
                !empty($body['published_year']) ? (int) $body['published_year'] : null,
                !empty($body['publisher'])   ? trim($body['publisher'])   : null,
                !empty($body['cover_url'])   ? trim($body['cover_url'])   : null,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Response::error('A book with this ISBN already exists.', 409);
            }
            throw $e;
        }
        $this->show((int) $this->db->lastInsertId());
    }

    /**
     * PUT /api/books/{id}  (admin)
     */
    public function update(int $id, array $body): void {
        Auth::requireRole('admin');

        $stmt = $this->db->prepare('SELECT * FROM books WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) Response::error('Book not found.', 404);

        $v = new Validator($body);
        $v->required('title')->required('author')
          ->required('category_id')->integer('category_id', 1)
          ->integer('total_copies', 1, 999, 'Total copies');
        $v->failFast();

        $newTotal = isset($body['total_copies']) ? (int) $body['total_copies'] : (int) $old['total_copies'];
        $diff     = $newTotal - (int) $old['total_copies'];
        $newAvail = max(0, (int) $old['available_copies'] + $diff);

        $stmt = $this->db->prepare("
            UPDATE books SET
              title=?, author=?, isbn=?, category_id=?, description=?,
              total_copies=?, available_copies=?, published_year=?, publisher=?, cover_url=?
            WHERE id=?");
        $stmt->execute([
            trim($body['title']  ?? $old['title']),
            trim($body['author'] ?? $old['author']),
            array_key_exists('isbn', $body)          ? ($body['isbn'] ?: null)        : $old['isbn'],
            (int) ($body['category_id']              ?? $old['category_id']),
            array_key_exists('description', $body)   ? ($body['description'] ?: null) : $old['description'],
            $newTotal, $newAvail,
            array_key_exists('published_year', $body) ? ($body['published_year'] ?: null) : $old['published_year'],
            array_key_exists('publisher', $body)      ? ($body['publisher']  ?: null)  : $old['publisher'],
            array_key_exists('cover_url', $body)      ? ($body['cover_url']  ?: null)  : $old['cover_url'],
            $id,
        ]);
        $this->show($id);
    }

    /**
     * DELETE /api/books/{id}  (admin)
     */
    public function delete(int $id): void {
        Auth::requireRole('admin');

        // Prevent deletion if active loans exist
        $loan = $this->db->prepare(
            "SELECT id FROM loans WHERE book_id = ? AND status IN ('active','overdue') LIMIT 1"
        );
        $loan->execute([$id]);
        if ($loan->fetch()) {
            Response::error('Cannot delete: book has active loans.', 409);
        }

        $stmt = $this->db->prepare('DELETE FROM books WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) Response::error('Book not found.', 404);

        Response::success('Book deleted successfully.');
    }
}
