<?php

namespace QUI\ERP\Accounting\Offers;

class Handler extends \QUI\Utils\Singleton
{
    public function offersTable(): string
    {
        return 'processes_offers_test';
    }

    public function temporaryOffersTable(): string
    {
        return 'processes_temporary_offers_test';
    }

    public function getOffer(int | string $id): Offer
    {
        throw new \QUI\Exception('Offer not found');
    }

    public function getTemporaryOffer(int | string $id): Offer
    {
        throw new \QUI\Exception('Temporary offer not found');
    }

    public function getOfferByHash(string $hash): Offer
    {
        throw new \QUI\Exception('Offer not found');
    }
}
