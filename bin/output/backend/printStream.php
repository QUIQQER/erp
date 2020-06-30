<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 6).'/header.php';

use QUI\ERP\Output\Output;
use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request    = QUI::getRequest();
$entityId   = Orthos::clear($Request->query->get('id'));
$entityType = Orthos::clear($Request->query->get('t'));
$template   = Orthos::clear($Request->query->get('tpl'));
$quiId      = Orthos::clear($Request->query->get('oid'));

try {
    $HtmlPdfDocument = Output::getDocumentPdf(
        $entityId,
        $entityType,
        null,
        null,
        $template ?: null
    );

    $imageFile = $HtmlPdfDocument->createImage(
        true,
        [
            '-flatten'  // removes background
        ]
    );
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
}

$Response = QUI::getGlobalResponse();
$Response->headers->set('Content-Type', 'image/jpg');
$Response->setContent(file_get_contents($imageFile));
$Response->send();

if (\file_exists($imageFile)) {
    \unlink($imageFile);
}
