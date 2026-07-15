<?php

namespace QUI\ERP\Products\Field;

class Field
{
    public function getId(): int
    {
        return 0;
    }

    public function getValue(): mixed
    {
        return null;
    }

    public function setValue(mixed $value): void
    {
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return [];
    }

    public function getOption(string $option): mixed
    {
        return null;
    }

    public function setOption(string $option, mixed $value): void
    {
    }

    public function isEmpty(): bool
    {
        return true;
    }

    public function save(): void
    {
    }
}
