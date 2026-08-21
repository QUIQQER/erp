<?php

namespace QUI\ERP\Accounting\Offers;

class Handler extends \QUI\Utils\Singleton
{
    public static string $offersTable = 'processes_offers_test';
    public static string $temporaryOffersTable = 'processes_temporary_offers_test';

    public function offersTable(): string
    {
        return self::$offersTable;
    }

    public function temporaryOffersTable(): string
    {
        return self::$temporaryOffersTable;
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
