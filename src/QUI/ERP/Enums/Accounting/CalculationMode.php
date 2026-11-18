<?php

namespace QUI\ERP\Enums\Accounting;

/**
 * The persisted calculation policy of a document's article list, independent of its calculation cache.
 */
enum CalculationMode: string
{
    case AUTOMATIC = 'automatic';
    case MANUAL = 'manual';
}
