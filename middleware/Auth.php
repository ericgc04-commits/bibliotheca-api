<?php
/**
 * middleware/Auth.php
 * JWT authentication and role-based authorisation middleware.
 */

class Auth {

    /**
     * Verifies the Bearer JWT in the Authorization header.
     * Returns the decoded payload array or sends 401 and exits.
     */
    public static function require(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? getallheaders()['Authorization'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            Response::error('Authentication required. Please provide a Bearer token.', 401);
        }

        $token = substr($header, 7);

        try {
            return JWT::decode($token);
        } catch (RuntimeException $e) {
            $msg = str_contains($e->getMessage(), 'expired')
                ? 'Token has expired. Please log in again.'
                : 'Invalid token.';
            Response::error($msg, 401);
        }
    }

    /**
     * Like require() but also checks that the user has one of the given roles.
     *
     * @param string ...$roles  Allowed roles (e.g. 'admin')
     */
    public static function requireRole(string ...$roles): array {
        $user = self::require();
        if (!in_array($user['role'] ?? '', $roles, true)) {
            Response::error('Forbidden. Insufficient permissions.', 403);
        }
        return $user;
    }
}
