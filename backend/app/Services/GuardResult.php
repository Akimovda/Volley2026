<?php
// app/Services/GuardResult.php

namespace App\Services;

final class GuardResult
{
    public bool $allowed = false;

    public array $errors = [];

    public array $data = [];

    public array $meta = [];

    private function __construct(
        bool $allowed,
        array $data = [],
        array $errors = [],
        array $meta = []
    ) {
        $defaults = [
            'is_registered' => false,
            'free_positions' => [],
        ];

        $this->allowed = $allowed;
        $this->errors  = $errors;
        $this->meta    = $meta;
        $this->data    = array_merge($defaults, $data);
    }

    public static function allow(array $data = [], array $meta = []): self
    {
        return new self(true, $data, [], $meta);
    }

    public static function deny(string $message, array $data = [], array $meta = []): self
    {
        return new self(false, $data, [$message], $meta);
    }

    public function addError(string $message): void
    {
        $this->allowed = false;
        $this->errors[] = $message;
    }

    /**
     * Для POST / API — жёстко запрещаем
     */
    public function authorize(): void
    {
        if (!$this->allowed) {
            abort(403, $this->errors[0] ?? 'Доступ запрещён');
        }
    }

    /**
     * Для blade / UI
     */
    public function firstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
}