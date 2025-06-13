<?php

namespace QUI\ERP\Enums\Payments;

enum EN16931: string
{
    case CASH = '10'; // Barzahlung
    case CHEQUE = '20'; // Scheck
    case CREDIT_CARD = '30'; // Kreditkarte
    case DEBIT_CARD = '31'; // Debitkarte
    case CREDIT_TRANSFER = '42'; // Überweisung
    case BANK_CARD = '48'; // Bankkarte
    case DIRECT_DEBIT = '49'; // Lastschrift
    case PAYMENT_TO_BANK_ACCOUNT = '57'; // Zahlung auf Bankkonto
    case SEPA_CREDIT_TRANSFER = '58'; // SEPA-Überweisung
    case SEPA_DIRECT_DEBIT = '59'; // SEPA-Lastschrift
    case NOT_DEFINED = '97'; // Nicht definiert
    case MUTUALLY_DEFINED = 'ZZ'; // Frei vereinbart
}
