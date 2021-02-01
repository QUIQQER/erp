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

$requestHash = \hash('sha256', \implode('', [$entityId, $entityType, $template, $templateProvider]));
$cacheName   = 'quiqqer/erp/print/'.$requestHash;

try {
    $HtmlPdfDocument = Output::getDocumentPdf(
        $entityId,
        $entityType,
        null,
        Output::getOutputTemplateProviderByPackage($templateProvider),
        $template ?: null
    );

    $imageFiles = $HtmlPdfDocument->createImage(
        true,
        [
            '-transparent-color',
            '-background white',
            '-alpha remove',
            '-alpha off',
            '-bordercolor white',
            '-border 10'
        ]
    );

    if (!\is_array($imageFiles)) {
        $imageFiles = [$imageFiles];
    }
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
}

QUI::getSession()->set($cacheName, \json_encode($imageFiles));

$baseUrl     = URL_OPT_DIR.'quiqqer/erp/bin/output/backend/printStream.php?';
$queryParams = [
    'hash'  => $cacheName,
    'index' => 0
];

// Retrieve all print images
$printImageSources = [];

foreach ($imageFiles as $imageFile) {
    $streamFile          = $baseUrl.\http_build_query($queryParams);
    $printImageSources[] = $streamFile;

    $queryParams['index']++;
}

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
    </style>
</head>
<body>';

foreach ($printImageSources as $imgContent) {
    echo '<img 
            class="pdfDocument" 
            src="'.$imgContent.'"
            style="width: 100%; width: 21cm; height: 29.7cm; padding: 1cm;"
        />';
}

echo '<script>
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
