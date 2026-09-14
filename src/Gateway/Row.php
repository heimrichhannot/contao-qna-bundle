<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Gateway;

final readonly class Row
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    public function int(string $column): int
    {
        $value = $this->data[$column] ?? null;

        if (!\is_int($value) && !\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not an integer value.', $column));
        }

        return (int) $value;
    }

    public function nullableInt(string $column): ?int
    {
        return null === ($this->data[$column] ?? null) ? null : $this->int($column);
    }

    public function string(string $column): string
    {
        $value = $this->data[$column] ?? null;

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not a string value.', $column));
        }

        return $value;
    }

    public function bool(string $column): bool
    {
        $value = $this->data[$column] ?? null;

        if (!\is_bool($value) && !\is_int($value) && !\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column "%s" is not a boolean value.', $column));
        }

        return (bool) $value;
    }
}
