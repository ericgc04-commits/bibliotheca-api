<?php
/**
 * utils/Response.php
 * Standardised JSON response helper.
 */

class Response {

    /** Send a success response. */
    public static function json(mixed $data, int $status = 200, string $message = ''): void {
        http_response_code($status);
        $body = ['success' => true];
        if ($message) $body['message'] = $message;

        // If $data is an array with 'data' key already, merge; otherwise wrap
        if (is_array($data) && array_key_exists('data', $data)) {
            $body = array_merge($body, $data);
        } else {
            $body['data'] = $data;
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Send a plain success message. */
    public static function success(string $message, int $status = 200, array $extra = []): void {
        http_response_code($status);
        echo json_encode(array_merge(['success' => true, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Send an error response. */
    public static function error(string $message, int $status = 400, array $errors = []): void {
        http_response_code($status);
        $body = ['success' => false, 'message' => $message];
        if ($errors) $body['errors'] = $errors;
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
