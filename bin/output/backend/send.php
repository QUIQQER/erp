<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 6).'/header.php';

use QUI\Utils\Security\Orthos;
use QUI\ERP\Output\Output;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request    = QUI::getRequest();
$entityId   = Orthos::clear($Request->query->get('id'));
$entityType = Orthos::clear($Request->query->get('t'));
$template   = Orthos::clear($Request->query->get('tpl'));
$quiId      = Orthos::clear($Request->query->get('oid'));
$recipient  = Orthos::clear($Request->query->get('recipient'));

$errorOutput = function ($message) use ($quiId) {
    echo '
    <script>
    var parent = window.parent;
    
    if (typeof parent.require !== "undefined") {
        parent.require(["qui/QUI"], function(QUI) {
            QUI.getMessageHandler().then(function(MH) {
                MH.addError("'.$message.'");
            });
            
            var Control = QUI.Controls.getById(\''.$quiId.'\');
            
            if (Control) {
                Control.Loader.hide();     
            }
        });
    }
    </script>';
    exit;
};

$successOutput = function ($message) use ($quiId) {
    echo '
    <script>
    var parent = window.parent;
    
    if (typeof parent.require !== "undefined") {
        parent.require(["qui/QUI"], function(QUI) {
            QUI.getMessageHandler().then(function(MH) {
                MH.addSuccess("'.$message.'");
            });
            
            var Control = QUI.Controls.getById(\''.$quiId.'\');
            
            if (Control) {
                Control.Loader.hide();     
            }
        });
    }
    </script>';
    exit;
};

try {
    Output::sendPdfViaMail(
        $entityId,
        $entityType,
        null,
        null,
        $template,
        $recipient ?: null
    );
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    $errorOutput($Exception->getMessage());
}

$successOutput(QUI::getLocale()->get('quiqqer/erp', 'Output.send.success'));
