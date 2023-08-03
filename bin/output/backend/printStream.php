<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 6) . '/header.php';

use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request = QUI::getRequest();
$hash = Orthos::clear($Request->query->get('hash'));
$index = (int)$Request->query->get('index');

if (empty($hash)) {
    exit;
}

$imageFiles = QUI::getSession()->get($hash);

if (empty($imageFiles)) {
    exit;
}

$imageFiles = \json_decode($imageFiles, true);

if (empty($imageFiles[$index]) || !\file_exists($imageFiles[$index])) {
    exit;
}

$image = $imageFiles[$index];

$Response = QUI::getGlobalResponse();
$Response->headers->set('Content-Type', 'image/jpg');
$Response->setContent(\file_get_contents($image));
$Response->send();

\unlink($image);
