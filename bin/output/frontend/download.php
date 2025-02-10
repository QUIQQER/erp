<?php

/**
 * This file contains the PDF download for an ERP Output document (frontend)
 * It opens the native download dialog
 */

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 6) . '/header.php';

use QUI\ERP\Output\Output;
use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();
$Request = QUI::getRequest();
$entityId = Orthos::clear($Request->query->get('id'));
$entityType = Orthos::clear($Request->query->get('t'));

try {
    $outputProvider = Output::getOutputProviderByEntityType($entityType);

    if (empty($outputProvider)) {
        exit;
    }

    $OutputProvider = new $outputProvider();

    if (!$OutputProvider::hasDownloadPermission($entityId, $User)) {
        exit;
    }

    $HtmlPdfDocument = Output::getDocumentPdf($entityId, $entityType, $OutputProvider);
    $HtmlPdfDocument->download();
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
}

exit;
