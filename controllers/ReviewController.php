<?php
/**
 * controllers/ReviewController.php
 */
class ReviewController {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function byBook(int $bookId): void {
        $stmt = $this->db->prepare("
            SELECT r.id, r.rating, r.comment, r.created_at, u.username, u.full_name
            FROM reviews r
            JOIN users u ON u.id = r.user_id
            WHERE r.book_id = ?
            ORDER BY r.created_at DESC");
        $stmt->execute([$bookId]);
        Response::json($stmt->fetchAll());
    }

    public function create(array $body): void {
        $user = Auth::require();
        $v = new Validator($body);
        $v->required('book_id')->integer('book_id', 1)
          ->required('rating')->rating('rating');
        $v->failFast();

        $bookId = (int) $body['book_id'];
        $bk = $this->db->prepare('SELECT id FROM books WHERE id = ?');
        $bk->execute([$bookId]);
        if (!$bk->fetch()) Response::error('Book not found.', 404);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO reviews (user_id, book_id, rating, comment) VALUES (?,?,?,?)'
            );
            $stmt->execute([$user['id'], $bookId, (int)$body['rating'], $body['comment'] ?? null]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Response::error('You have already reviewed this book.', 409);
            }
            throw $e;
        }
        Response::success('Review submitted successfully.', 201);
    }

    public function delete(int $id): void {
        $user = Auth::require();
        $stmt = $this->db->prepare('SELECT * FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $review = $stmt->fetch();
        if (!$review) Response::error('Review not found.', 404);
        if ($user['role'] !== 'admin' && (int)$review['user_id'] !== (int)$user['id']) {
            Response::error('Not authorised.', 403);
        }
        $this->db->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        Response::success('Review deleted.');
    }
}
