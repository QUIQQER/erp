<?php

namespace QUITests\ERP;

class AjaxOutputEntityFixture
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUUID(): string
    {
        return 'ajax-output-' . $this->id;
    }

    public function getPrefixedNumber(): string
    {
        return 'AJAX-' . $this->id;
    }
}
