<?php
// ─────────────────────────────────────────────
//  app/core/Validator.php  –  Validación de entrada
//  Equivalente a los schemas Pydantic de FastAPI
// ─────────────────────────────────────────────

namespace App\Core;

class Validator
{
    private array $errors  = [];
    private array $data    = [];
    private array $raw     = [];

    public function __construct(array $raw)
    {
        $this->raw  = $raw;
        $this->data = $raw;
    }

    // ── Reglas ────────────────────────────────

    public function required(string $field): static
    {
        if (!isset($this->raw[$field]) || $this->raw[$field] === '' || $this->raw[$field] === null) {
            $this->errors[$field][] = "El campo '{$field}' es requerido";
        }
        return $this;
    }

    public function string(string $field, int $min = 1, int $max = 255): static
    {
        if (!isset($this->raw[$field])) return $this;
        $val = $this->raw[$field];
        if (!is_string($val)) {
            $this->errors[$field][] = "'{$field}' debe ser texto";
        } elseif (strlen($val) < $min || strlen($val) > $max) {
            $this->errors[$field][] = "'{$field}' debe tener entre {$min} y {$max} caracteres";
        }
        return $this;
    }

    public function email(string $field): static
    {
        if (!isset($this->raw[$field])) return $this;
        if (!filter_var($this->raw[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "'{$field}' debe ser un email válido";
        }
        return $this;
    }

    public function integer(string $field, ?int $min = null, ?int $max = null): static
    {
        if (!isset($this->raw[$field])) return $this;
        $val = $this->raw[$field];
        if (!is_numeric($val) || (int)$val != $val) {
            $this->errors[$field][] = "'{$field}' debe ser un entero";
            return $this;
        }
        $this->data[$field] = (int)$val;
        if ($min !== null && (int)$val < $min) {
            $this->errors[$field][] = "'{$field}' debe ser >= {$min}";
        }
        if ($max !== null && (int)$val > $max) {
            $this->errors[$field][] = "'{$field}' debe ser <= {$max}";
        }
        return $this;
    }

    public function boolean(string $field): static
    {
        if (!isset($this->raw[$field])) return $this;
        $val = $this->raw[$field];
        if (!is_bool($val) && !in_array($val, [0, 1, '0', '1', 'true', 'false'], true)) {
            $this->errors[$field][] = "'{$field}' debe ser booleano";
        } else {
            $this->data[$field] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }
        return $this;
    }

    public function date(string $field, string $format = 'Y-m-d'): static
    {
        if (!isset($this->raw[$field])) return $this;
        $d = \DateTime::createFromFormat($format, $this->raw[$field]);
        if (!$d || $d->format($format) !== $this->raw[$field]) {
            $this->errors[$field][] = "'{$field}' debe ser fecha con formato {$format}";
        }
        return $this;
    }

    public function time(string $field): static
    {
        if (!isset($this->raw[$field])) return $this;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $this->raw[$field])) {
            $this->errors[$field][] = "'{$field}' debe ser hora HH:MM o HH:MM:SS";
        }
        return $this;
    }

    public function inList(string $field, array $allowed): static
    {
        if (!isset($this->raw[$field])) return $this;
        if (!in_array($this->raw[$field], $allowed, true)) {
            $this->errors[$field][] = "'{$field}' debe ser uno de: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function uuid(string $field): static
    {
        if (!isset($this->raw[$field])) return $this;
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($pattern, $this->raw[$field])) {
            $this->errors[$field][] = "'{$field}' debe ser un UUID válido";
        }
        return $this;
    }

    // ── Resultado ─────────────────────────────

    /**
     * Dispara Response::validationError si hay errores (igual que FastAPI 422)
     */
    public function validate(): array
    {
        if (!empty($this->errors)) {
            Response::validationError($this->errors);
        }
        return $this->data;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Factory: parsea el body JSON de la request actual
     */
    public static function fromBody(): static
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        return new static($body);
    }

    /**
     * Factory: desde $_GET (query params)
     */
    public static function fromQuery(): static
    {
        return new static($_GET);
    }
}
