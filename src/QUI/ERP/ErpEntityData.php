<?php

namespace QUI\ERP;

trait ErpEntityData
{
    public function getReferenceData(): array
    {
        return [
            'id' => $this->getUUID(),
            'id_str' => $this->getPrefixedNumber(), // old
            'prefixedNumber' => $this->getPrefixedNumber(),
            'uuid' => $this->getUUID(),
            'globalProcessId' => $this->getGlobalProcessId(),
            'currentStatusId' => $this->getCurrentStatusId(),
        ];
    }
}
