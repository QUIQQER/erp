<?php

namespace QUI\ERP;

use function method_exists;

trait ErpEntityData
{
    public function getReferenceData(): array
    {
        $currentStatus = null;

        if (method_exists($this, 'getCurrentStatusId')) {
            $currentStatus = $this->getCurrentStatusId();
        }

        return [
            'id' => $this->getUUID(),
            'id_str' => $this->getPrefixedNumber(), // old
            'prefixedNumber' => $this->getPrefixedNumber(),
            'uuid' => $this->getUUID(),
            'globalProcessId' => $this->getGlobalProcessId(),
            'currentStatusId' => $currentStatus
        ];
    }
}
