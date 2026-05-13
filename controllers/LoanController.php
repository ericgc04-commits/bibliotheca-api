<?php
/**
 * controllers/LoanController.php
 * Book borrowing and return, with DB transaction safety.
 */

class LoanController {

    private PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * GET /api/loans
     * Admins see all; members see only their own.
     */
    public function index(): void {
        $user   = Auth::require();
        $status = $_GET['status'] ?? '';
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Auto-mark overdue loans
        $this->db->exec("UPDATE loans SET status='overdue' WHERE due_date < CURDATE() AND status='active'");

        $where  = 'WHERE 1=1';
        $params = [];

        if ($user['role'] !== 'admin') {
            $where   .= ' AND l.user_id = ?';
            $params[] = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            $where   .= ' AND l.user_id = ?';
            $params[] = (int) $_GET['user_id'];
        }

        if ($status !== '') {
            $where   .= ' AND l.status = ?';
            $params[] = $status;
        }

        $total = (int) $this->db->prepare("SELECT COUNT(*) FROM loans l $where")
                                 ->execute($params) ?: 0;
        // Use a separate prepare for the count
        $cStmt = $this->db->prepare("SELECT COUNT(*) FROM loans l $where");
        $cStmt->execute($params);
        $total = (int) $cStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT l.*, b.title AS book_title, b.author AS book_author,
                   u.username, u.full_name,
                   DATEDIFF(CURDATE(), l.due_date) AS days_overdue
            FROM loans l
            JOIN books b ON b.id = l.book_id
            JOIN users u ON u.id = l.user_id
            $where
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?");
        $stmt->execute(array_merge($params, [$limit, $offset]));

        Response::json([
            'data'       => $stmt->fetchAll(),
            'pagination' => [
                'total'      => $total,
                'page'       => $page,
                'limit'      => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    /**
     * GET /api/loans/{id}
     */
    public function show(int $id): void {
        $user = Auth::require();

        $stmt = $this->db->prepare("
            SELECT l.*, b.title AS book_title, b.author AS book_author,
                   u.username, u.full_name, u.email
            FROM loans l
            JOIN books b ON b.id = l.book_id
            JOIN users u ON u.id = l.user_id
            WHERE l.id = ?");
        $stmt->execute([$id]);
        $loan = $stmt->fetch();

        if (!$loan) Response::error('Loan not found.', 404);
        if ($user['role'] !== 'admin' && (int) $loan['user_id'] !== (int) $user['id']) {
            Response::error('Forbidden.', 403);
        }
        Response::json($loan);
    }

    /**
     * POST /api/loans  — borrow a book
     */
    public function create(array $body): void {
        $user = Auth::require();

        $v = new Validator($body);
        $v->required('book_id')->integer('book_id', 1);
        if (!empty($body['due_date'])) $v->date('due_date');
        $v->failFast();

        $bookId  = (int) $body['book_id'];
        $userId  = ($user['role'] === 'admin' && !empty($body['user_id']))
                   ? (int) $body['user_id']
                   : (int) $user['id'];

        $this->db->beginTransaction();
        try {
            // Lock the book row
            $bStmt = $this->db->prepare('SELECT id, title, available_copies FROM books WHERE id = ? FOR UPDATE');
            $bStmt->execute([$bookId]);
            $book = $bStmt->fetch();
            if (!$book) {
                $this->db->rollBack();
                Response::error('Book not found.', 404);
            }
            if ((int) $book['available_copies'] <= 0) {
                $this->db->rollBack();
                Response::error('No copies of this book are currently available.', 409);
            }

            // Check for existing active loan
            $aStmt = $this->db->prepare(
                "SELECT id FROM loans WHERE user_id=? AND book_id=? AND status IN ('active','overdue') LIMIT 1"
            );
            $aStmt->execute([$userId, $bookId]);
            if ($aStmt->fetch()) {
                $this->db->rollBack();
                Response::error('You already have an active loan for this book.', 409);
            }

            $loanDate = date('Y-m-d');
            $dueDate  = !empty($body['due_date'])
                        ? $body['due_date']
                        : date('Y-m-d', strtotime('+14 days'));

            $iStmt = $this->db->prepare(
                'INSERT INTO loans (user_id, book_id, loan_date, due_date, notes) VALUES (?,?,?,?,?)'
            );
            $iStmt->execute([$userId, $bookId, $loanDate, $dueDate, $body['notes'] ?? null]);
            $loanId = (int) $this->db->lastInsertId();

            $this->db->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = ?')
                     ->execute([$bookId]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->show($loanId);
    }

    /**
     * PATCH /api/loans/{id}/return
     */
    public function returnBook(int $id): void {
        $user = Auth::require();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM loans WHERE id = ? AND status IN ('active','overdue') FOR UPDATE"
            );
            $stmt->execute([$id]);
            $loan = $stmt->fetch();

            if (!$loan) {
                $this->db->rollBack();
                Response::error('Active loan not found.', 404);
            }
            if ($user['role'] !== 'admin' && (int) $loan['user_id'] !== (int) $user['id']) {
                $this->db->rollBack();
                Response::error('Not authorised to return this loan.', 403);
            }

            $returnDate = date('Y-m-d');
            $this->db->prepare("UPDATE loans SET status='returned', return_date=? WHERE id=?")
                     ->execute([$returnDate, $id]);
            $this->db->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = ?')
                     ->execute([$loan['book_id']]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Response::success('Book returned successfully.', 200, ['return_date' => $returnDate]);
    }
}
