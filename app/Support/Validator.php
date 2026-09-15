<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validation des formulaires du back-office.
 *
 *   $v = new Validator($request->all());
 *   $v->required('name')->maxLength('name', 100)->slug('slug')->integer('sort_order', -32768, 32767);
 *   if ($v->fails()) { … $v->errors() … }
 *   $name = $v->string('name');
 *
 * Messages traduits (lang : validation.*). Une seule erreur conservée par champ (la première).
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    public function required(string ...$fields): self
    {
        foreach ($fields as $field) {
            if ($this->string($field) === '') {
                $this->add($field, __('validation.required'));
            }
        }

        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (mb_strlen($this->string($field)) > $max) {
            $this->add($field, __('validation.max_length', ['max' => $max]));
        }

        return $this;
    }

    public function slug(string $field): self
    {
        $value = $this->string($field);
        if ($value !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            $this->add($field, __('validation.slug'));
        }

        return $this;
    }

    public function code(string $field): self
    {
        $value = $this->string($field);
        if ($value !== '' && preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
            $this->add($field, __('validation.code'));
        }

        return $this;
    }

    public function integer(string $field, ?int $min = null, ?int $max = null): self
    {
        $value = $this->string($field);
        if ($value === '') {
            return $this;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $this->add($field, __('validation.integer'));
        }

        return $this->range($field, (float) $value, $min, $max);
    }

    public function decimal(string $field, ?float $min = null, ?float $max = null): self
    {
        $value = str_replace(',', '.', $this->string($field));
        if ($value === '') {
            return $this;
        }
        if (!is_numeric($value)) {
            return $this->add($field, __('validation.decimal'));
        }

        return $this->range($field, (float) $value, $min, $max);
    }

    /** @param list<string|int> $allowed */
    public function in(string $field, array $allowed): self
    {
        $value = $this->string($field);
        if ($value !== '' && !in_array($value, array_map('strval', $allowed), true)) {
            $this->add($field, __('validation.in'));
        }

        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->string($field);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->add($field, __('validation.email'));
        }

        return $this;
    }

    public function phone(string $field): self
    {
        $value = $this->string($field);
        if ($value !== '' && preg_match('/^\+?[0-9 ().-]{6,30}$/', $value) !== 1) {
            $this->add($field, __('validation.phone'));
        }

        return $this;
    }

    public function hostname(string $field): self
    {
        $value = $this->string($field);
        if ($value !== '' && (filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || str_contains($value, '/'))) {
            $this->add($field, __('validation.hostname'));
        }

        return $this;
    }

    /** Règle libre : $valid faux → message sur le champ. */
    public function rule(string $field, bool $valid, string $message): self
    {
        if (!$valid) {
            $this->add($field, $message);
        }

        return $this;
    }

    public function add(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    // Lecture des valeurs nettoyées -----------------------------------------------------------

    public function string(string $field): string
    {
        $value = $this->input[$field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function nullableString(string $field): ?string
    {
        $value = $this->string($field);

        return $value === '' ? null : $value;
    }

    public function int(string $field, int $default = 0): int
    {
        $value = filter_var($this->string($field), FILTER_VALIDATE_INT);

        return $value === false ? $default : $value;
    }

    public function nullableInt(string $field): ?int
    {
        $value = filter_var($this->string($field), FILTER_VALIDATE_INT);

        return $value === false ? null : $value;
    }

    public function nullableDecimal(string $field): ?string
    {
        $value = str_replace(',', '.', $this->string($field));

        return is_numeric($value) ? $value : null;
    }

    public function bool(string $field): bool
    {
        return in_array($this->string($field), ['1', 'on', 'true'], true);
    }

    /** @return list<string> */
    public function list(string $field): array
    {
        $value = $this->input[$field] ?? [];

        return is_array($value) ? array_values(array_filter(array_map(static fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $value), static fn (string $v): bool => $v !== '')) : [];
    }

    private function range(string $field, float $value, int|float|null $min, int|float|null $max): self
    {
        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            $this->add($field, match (true) {
                $min !== null && $max !== null => __('validation.between', ['min' => $min, 'max' => $max]),
                $min !== null => __('validation.min', ['min' => $min]),
                default => __('validation.max', ['max' => $max]),
            });
        }

        return $this;
    }
}
