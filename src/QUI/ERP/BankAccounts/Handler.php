<?php

namespace QUI\ERP\BankAccounts;

use Exception;
use QUI;

use function json_decode;
use function json_encode;
use function mt_rand;

/**
 * Class Handler.
 *
 * Main handler for bank accounts.
 */
class Handler
{
    /**
     * Add bank account to list.
     *
     * @param array $data
     * @return array - New bank account data
     *
     * @throws QUI\Exception
     */
    public static function addBankAccount(array $data): array
    {
        $fields = [
            'title'              => true,
            'name'               => true,
            'iban'               => true,
            'bic'                => true,
            'accountHolder'      => true,
            'creditorId'         => false,
            'default'            => false,
            'financialAccountNo' => false
        ];

        $bankAccount = [];

        foreach ($fields as $field => $isRequired) {
            if ($isRequired && empty($data[$field])) {
                throw new QUI\Exception('Cannot add bank account. Required field "' . $field . '" is empty.');
            }

            $bankAccount[$field] = !empty($data[$field]) ? $data[$field] : '';
        }

        $list = self::getList();

        do {
            $id = mt_rand(10000, 99999);
        } while (!empty($list[$id]));

        $Conf = QUI::getPackage('quiqqer/erp')->getConfig();

        $bankAccount['id'] = $id;
        $list[$id]         = $bankAccount;

        $Conf->setValue('bankAccounts', 'accounts', json_encode($list));
        $Conf->save();

        return $bankAccount;
    }

    /**
     * Get data of the bank account that is set as the company default.
     *
     * @return array|false
     */
    public static function getCompanyBankAccount()
    {
        try {
            $bankAccounts  = self::getList();
            $bankAccountId = QUI::getPackage('quiqqer/erp')->getConfig()->get('company', 'bankAccountId');
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        if (!empty($bankAccounts[$bankAccountId])) {
            return $bankAccounts[$bankAccountId];
        }

        return self::getDefaultBankAccount();
    }

    /**
     * Get the bank account data of the default bank account.
     *
     * @return array|false
     */
    public static function getDefaultBankAccount()
    {
        try {
            $bankAccounts = self::getList();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        foreach ($bankAccounts as $bankAccount) {
            if (!empty($bankAccount['default'])) {
                return $bankAccount;
            }
        }

        return false;
    }

    /**
     * Get the bank account data by id.
     *
     * @return array|false
     */
    public static function getBankAccountById(int $id)
    {
        try {
            $bankAccounts = self::getList();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        foreach ($bankAccounts as $bankAccount) {
            if ((int)$bankAccount['id'] === $id) {
                return $bankAccount;
            }
        }

        return false;
    }

    /**
     * Get list of bank accounts.
     *
     * @return array
     */
    public static function getList(): array
    {
        try {
            $config = self::getConfig();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        $bankAccounts = $config['accounts'];

        if (empty($bankAccounts)) {
            return [];
        }

        return json_decode($bankAccounts, true);
    }

    /**
     * @return array
     * @throws QUI\Exception
     */
    protected static function getConfig(): array
    {
        $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
        return $Conf->getSection('bankAccounts');
    }
}
