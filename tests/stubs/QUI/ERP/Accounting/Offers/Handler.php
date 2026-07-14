<?php

namespace QUI\ERP\Accounting\Offers;

class Handler
{
    public static function getInstance(): self
    {
    }

    public function offersTable(): string
    {
    }

    public function temporaryOffersTable(): string
    {
    }

    public function getOffer(int | string $id): Offer
    {
    }

    public function getTemporaryOffer(int | string $id): Offer
    {
    }

    public function getOfferByHash(string $hash): Offer
    {
    }
}
