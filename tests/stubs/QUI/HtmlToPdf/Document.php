<?php

namespace QUI\HtmlToPdf;

class Document
{
    /** @param array<string, mixed>|null $options */
    public function __construct(?array $options = null)
    {
    }

    public function createPDF(): string
    {
        return '';
    }

    public function setAttribute(string $name, mixed $value): void
    {
    }

    public function setHeaderHTML(string $html): void
    {
    }

    public function setContentHTML(string $html): void
    {
    }

    public function setFooterHTML(string $html): void
    {
    }
}
