<?php

namespace QUI\ERP\Areas;

class Handler
{
    public function getChild(int | string $id): Area
    {
        return new Area();
    }
}
