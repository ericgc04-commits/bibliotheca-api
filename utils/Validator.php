<?php
/**
 * utils/Validator.php
 * Lightweight input validation helper.
 */

class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    /** Require a field to be non-empty after trimming. */
    public function required(string $field, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = trim($this->data[$field] ?? '');
        if ($val === '') {
            $this->errors[] = ['field' => $field, 'message' => "$label is required"];
        }
        return $this;
    }

    /** Minimum string length. */
    public function minLength(string $field, int $min, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) < $min) {
            $this->errors[] = ['field' => $field, 'message' => "$label must be at least $min characters"];
        }
        return $this;
    }

    /** Maximum string length. */
    public function maxLength(string $field, int $max, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) > $max) {
            $this->errors[] = ['field' => $field, 'message' => "$label must not exceed $max characters"];
        }
        return $this;
    }

    /** Valid email. */
    public function email(string $field): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = ['field' => $field, 'message' => 'Please enter a valid email address'];
        }
        return $this;
    }

    /** Password complexity: 8+ chars, 1 uppercase, 1 digit. */
    public function password(string $field): self {
        $val = $this->data[$field] ?? '';
        if ($val !== '') {
            if (strlen($val) < 8) {
                $this->errors[] = ['field' => $field, 'message' => 'Password must be at least 8 characters'];
            } elseif (!preg_match('/[A-Z]/', $val)) {
                $this->errors[] = ['field' => $field, 'message' => 'Password must contain at least one uppercase letter'];
            } elseif (!preg_match('/\d/', $val)) {
                $this->errors[] = ['field' => $field, 'message' => 'Password must contain at least one digit'];
            }
        }
        return $this;
    }

    /** Numeric integer. */
    public function integer(string $field, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '') {
            if (!ctype_digit((string)$val) || (int)$val < $min || (int)$val > $max) {
                $this->errors[] = ['field' => $field, 'message' => "$label must be an integer between $min and $max"];
            }
        }
        return $this;
    }

    /** Rating between 1 and 5. */
    public function rating(string $field): self {
        return $this->integer($field, 1, 5, 'Rating');
    }

    /** Valid ISO date (YYYY-MM-DD). */
    public function date(string $field): self {
        $val = $this->data[$field] ?? '';
        if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $this->errors[] = ['field' => $field, 'message' => 'Date must be in YYYY-MM-DD format'];
        }
        return $this;
    }

    public function hasErrors(): bool { return !empty($this->errors); }
    public function getErrors(): array { return $this->errors; }

    /** Throw a 422 response if validation failed. */
    public function failFast(): void {
        if ($this->hasErrors()) {
            Response::error('Validation failed', 422, $this->errors);
        }
    }
}
