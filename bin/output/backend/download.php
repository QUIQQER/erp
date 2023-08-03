<?php

/**
 * This file contains the PDF download for an ERP Output document
 * It opens the native download dialog
 */

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 6) . '/header.php';

use QUI\ERP\Output\Output;
use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request = QUI::getRequest();
$entityId = Orthos::clear($Request->query->get('id'));
$entityType = Orthos::clear($Request->query->get('t'));
$template = Orthos::clear($Request->query->get('tpl'));
$templateProvider = Orthos::clear($Request->query->get('tplpr'));
$quiId = Orthos::clear($Request->query->get('oid'));

$errorOutput = function ($message) use ($quiId) {
    echo '
    <script>
    var parent = window.parent;
    
    if (typeof parent.require !== "undefined") {
        parent.require(["qui/QUI"], function(QUI) {
            QUI.getMessageHandler().then(function(MH) {
                MH.addError("' . $message . '");
            });
            
            var Control = QUI.Controls.getById(\'' . $quiId . '\');
            
            if (Control) {
                Control.Loader.hide();     
            }
        });
    }
    </script>';
    exit;
};

try {
    $HtmlPdfDocument = Output::getDocumentPdf(
        $entityId,
        $entityType,
        null,
        Output::getOutputTemplateProviderByPackage($templateProvider),
        $template ?: null
    );

    $HtmlPdfDocument->download();
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);

    $message = $Exception->getMessage();
    $message = QUI\Utils\Security\Orthos::clear($message);

    $errorOutput($message);
}
