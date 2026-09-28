<?php

namespace QUI\ERP\Output;

use QUI;
use Symfony\Component\HttpFoundation\Response;

final class BackendOutputResponse
{
    public static function permissionDenied(): Response
    {
        $message = QUI::getLocale()->get('quiqqer/core', 'exception.no.permission');
        $query = QUI::getRequest()->query->all();
        $controlId = is_string($query['oid'] ?? null) ? $query['oid'] : '';
        $text = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
        $messageJson = json_encode($message, $jsonFlags);
        $controlJson = json_encode($controlId, $jsonFlags);

        // Output actions use hidden frames, so also show the error in the parent dialog.
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>403 Forbidden</title></head>
<body>
<p role="alert">{$text}</p>
<script>
if (window.parent !== window && typeof window.parent.require === 'function') {
    window.parent.require(['qui/QUI'], function(QUI) {
        const Control = QUI.Controls.getById({$controlJson});

        if (Control) {
            Control.Loader.hide();
        }

        QUI.getMessageHandler().then(function(Messages) {
            Messages.addError({$messageJson});
        }).catch(function(error) {
            console.error(error);
        });
    });
}
</script>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_FORBIDDEN, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private'
        ]);
    }
}
