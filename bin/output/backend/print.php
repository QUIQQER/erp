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

$Request          = QUI::getRequest();
$entityId         = Orthos::clear($Request->query->get('id'));
$entityType       = Orthos::clear($Request->query->get('t'));
$template         = Orthos::clear($Request->query->get('tpl'));
$templateProvider = Orthos::clear($Request->query->get('tplpr'));
$quiId            = Orthos::clear($Request->query->get('oid'));

$streamFile = URL_OPT_DIR.'quiqqer/erp/bin/output/backend/printStream.php?';
$streamFile .= \http_build_query([
    'id'    => $entityId,
    't'     => $entityType,
    'tpl'   => $template,
    'tplpr' => $templateProvider,
    'oid'   => $quiId
]);

echo '
<html>
<head>
    <style>
        body, html {
            margin: 0 !important;
            padding: 0 !important;
        }
 
        @page {
            margin: 0 !important;
        }
        
        .container {
            padding: 2.5em;
            width: calc(100% - 5em);
        }
    </style>
</head>
<body>
    <div class="container">
        <img 
            id="pdfDocument" 
            src="'.$streamFile.'"  
            style="max-width: 100%;"
           
        />
    </div>
    <script>
        var i, len, parts;
        
        var search = {};
        var sData = window.location.search.replace("?", "").split("&");
        
        for (i = 0, len = sData.length; i <len; i++) {
            parts = sData[i].split("=");
            search[parts[0]] = parts[1];            
        }
        
        var invoiceId = search["invoiceId"];
        var objectId = search["oid"];
        var parent = window.parent;
        
        window.onload = function() {
            window.print();
            parent.QUI.Controls.getById(objectId).$onPrintFinish(invoiceId);
        }
    </script>
</body>
</html>
<!--<script>window.print()</script>-->';
