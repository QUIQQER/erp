<?php

namespace QUI\ERP\Products\Product;

class UniqueProduct
{
    /** @return array<int, mixed> */
    public function getCustomFields(): array
    {
        return [];
    }

    public function getField(int $fieldId): ?\QUI\ERP\Products\Field\Field
    {
        return new \QUI\ERP\Products\Field\Field();
    }

    public function setQuantity(float | int $quantity): void
    {
    }

    public function calc(mixed $Calc = null): static
    {
        return $this;
    }

    public function toArticle(
        ?\QUI\Locale $Locale = null,
        bool $fieldsAreChangeable = true
    ): \QUI\ERP\Accounting\ArticleInterface {
        return new \QUI\ERP\Accounting\Article();
    }
}
