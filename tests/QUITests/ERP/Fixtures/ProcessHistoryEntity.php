<?php

namespace QUITests\ERP;

use DateTimeImmutable;
use QUI\ERP\Comments;

class ProcessHistoryEntity
{
    /** @param list<array<string, mixed>> $history */
    public function __construct(
        private string $hash,
        private string $number,
        private string $date,
        private array $history
    ) {
    }

    public function getUUID(): string
    {
        return $this->hash;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getPrefixedNumber(): string
    {
        return $this->number;
    }

    public function getAttribute(string $key): mixed
    {
        return match ($key) {
            'date', 'c_date' => $this->date,
            default => null
        };
    }

    public function getCreateDate(): string|DateTimeImmutable
    {
        if (str_starts_with($this->number, 'BOOK')) {
            return new DateTimeImmutable($this->date);
        }

        return $this->date;
    }

    public function getHistory(): Comments
    {
        return new Comments($this->history);
    }
}
