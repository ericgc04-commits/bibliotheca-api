<?php
/**
 * controllers/StatsController.php
 * Complex multi-table aggregation for the admin dashboard.
 */
class StatsController {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function overview(): void {
        Auth::requireRole('admin');

        // Single-query overview using subqueries
        $counts = $this->db->query("
            SELECT
              (SELECT COUNT(*) FROM books)                              AS total_books,
              (SELECT IFNULL(SUM(available_copies),0) FROM books)       AS available_copies,
              (SELECT COUNT(*) FROM users WHERE role='member')          AS total_members,
              (SELECT COUNT(*) FROM loans WHERE status='active')        AS active_loans,
              (SELECT COUNT(*) FROM loans WHERE status='overdue')       AS overdue_loans,
              (SELECT COUNT(*) FROM loans WHERE status='returned')      AS returned_loans,
              (SELECT COUNT(*) FROM loans)                              AS total_loans
        ")->fetch();

        // Top 5 most-borrowed books
        $popular = $this->db->query("
            SELECT b.id, b.title, b.author,
                   COUNT(l.id)                             AS loan_count,
                   ROUND(IFNULL(AVG(r.rating),0),1)        AS avg_rating
            FROM books b
            LEFT JOIN loans   l ON l.book_id = b.id
            LEFT JOIN reviews r ON r.book_id = b.id
            GROUP BY b.id
            ORDER BY loan_count DESC, avg_rating DESC
            LIMIT 5")->fetchAll();

        // Category breakdown
        $categories = $this->db->query("
            SELECT c.name,
                   COUNT(b.id) AS book_count,
                   IFNULL(SUM(b.total_copies - b.available_copies),0) AS copies_on_loan
            FROM categories c
            LEFT JOIN books b ON b.category_id = c.id
            GROUP BY c.id
            ORDER BY book_count DESC")->fetchAll();

        // Loans per month (last 6 months)
        $monthly = $this->db->query("
            SELECT DATE_FORMAT(loan_date,'%Y-%m') AS month, COUNT(*) AS count
            FROM loans
            WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month")->fetchAll();

        Response::json([
            'data' => [
                'overview'       => $counts,
                'popularBooks'   => $popular,
                'categoryStats'  => $categories,
                'monthlyLoans'   => $monthly,
            ]
        ]);
    }

    public function overdue(): void {
        Auth::requireRole('admin');
        $this->db->exec("UPDATE loans SET status='overdue' WHERE due_date<CURDATE() AND status='active'");
        $stmt = $this->db->query("
            SELECT l.id, l.loan_date, l.due_date,
                   DATEDIFF(CURDATE(), l.due_date) AS days_overdue,
                   b.title, b.author,
                   u.username, u.email, u.full_name
            FROM loans l
            JOIN books b ON b.id = l.book_id
            JOIN users u ON u.id = l.user_id
            WHERE l.status = 'overdue'
            ORDER BY days_overdue DESC");
        $rows = $stmt->fetchAll();
        Response::json(['data' => $rows, 'count' => count($rows)]);
    }
}
