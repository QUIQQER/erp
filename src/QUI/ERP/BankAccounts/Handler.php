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
     * @param array<mixed> $data
     * @return array<mixed> - New bank account data
     *
     * @throws QUI\Exception
     */
    public static function addBankAccount(array $data): array
    {
        $bankAccount = self::validateBankAccount($data);
        $list = self::getList();

        do {
            $id = mt_rand(10000, 99999);
        } while (isset($list[$id]));

        $bankAccount['id'] = $id;
        self::storeBankAccount($list, $bankAccount);

        return $bankAccount;
    }

    /**
     * Update an existing account without replacing other accounts.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws QUI\Exception
     */
    public static function updateBankAccount(int $id, array $data): array
    {
        $list = self::getList();

        if (!isset($list[$id])) {
            throw new QUI\Exception('Bank account not found.');
        }

        $bankAccount = array_merge($list[$id], self::validateBankAccount($data));
        $bankAccount['id'] = $id;
        self::storeBankAccount($list, $bankAccount);

        return $bankAccount;
    }

    /**
     * Delete an account immediately. Other accounts keep their default selection.
     *
     * @throws QUI\Exception
     */
    public static function deleteBankAccount(int $id): void
    {
        $list = self::getList();

        if (!isset($list[$id])) {
            throw new QUI\Exception('Bank account not found.');
        }

        unset($list[$id]);
        self::saveList($list);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws QUI\Exception
     */
    private static function validateBankAccount(array $data): array
    {
        $fields = [
            'title' => true,
            'name' => true,
            'iban' => true,
            'bic' => true,
            'accountHolder' => true,
            'creditorId' => false,
            'financialAccountNo' => false
        ];
        $bankAccount = [];

        foreach ($fields as $field => $isRequired) {
            $value = $data[$field] ?? '';

            // Legacy configuration readers return false for unset optional values.
            if (!$isRequired && $value === false) {
                $value = '';
            }

            if (!is_string($value) && !is_int($value)) {
                throw new QUI\Exception('Invalid bank account field "' . $field . '".');
            }

            $value = trim((string)$value);

            if ($isRequired && $value === '') {
                throw new QUI\Exception('Required bank account field "' . $field . '" is empty.');
            }

            $bankAccount[$field] = $value;
        }

        $default = $data['default'] ?? false;

        if (!in_array($default, [true, false, 0, 1, '0', '1', ''], true)) {
            throw new QUI\Exception('Invalid default bank account selection.');
        }

        $bankAccount['default'] = (bool)$default;

        return $bankAccount;
    }

    /**
     * @param array<mixed> $list
     * @param array<mixed> $bankAccount
     * @throws QUI\Exception
     */
    private static function storeBankAccount(array $list, array $bankAccount): void
    {
        if ($bankAccount['default']) {
            foreach ($list as &$account) {
                $account['default'] = false;
            }

            unset($account);
        }

        $list[$bankAccount['id']] = $bankAccount;
        self::saveList($list);
    }

    /**
     * @param array<mixed> $list
     * @throws QUI\Exception
     */
    private static function saveList(array $list): void
    {
        $Conf = QUI::getPackage('quiqqer/erp')->getConfig();

        if ($Conf === null) {
            throw new QUI\Exception('ERP configuration is not available');
        }

        $Conf->setValue('bankAccounts', 'accounts', json_encode($list, JSON_THROW_ON_ERROR));
        $Conf->save();
    }

    /**
     * Get data of the bank account that is set as the company default.
     *
     * @return array<mixed>|false
     */
    public static function getCompanyBankAccount(): bool|array
    {
        try {
            $bankAccounts = self::getList();
            $bankAccountId = QUI::getPackage('quiqqer/erp')->getConfig()?->get('company', 'bankAccountId');
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
     * @return array<mixed>|false
     */
    public static function getDefaultBankAccount(): bool|array
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
     * @return array<mixed>|false
     */
    public static function getBankAccountById(int $id): bool|array
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
     * @return array<mixed>
     */
    public static function getList(): array
    {
        try {
            $config = self::getConfig();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        $bankAccounts = $config['accounts'] ?? '';

        if (empty($bankAccounts)) {
            return [];
        }

        $list = json_decode($bankAccounts, true);

        if (!is_array($list)) {
            throw new QUI\Exception('Invalid bank account configuration.');
        }

        return $list;
    }

    /**
     * @return array<mixed>
     * @throws QUI\Exception
     */
    protected static function getConfig(): array
    {
        $Conf = QUI::getPackage('quiqqer/erp')->getConfig();

        if ($Conf === null) {
            throw new QUI\Exception('ERP configuration is not available');
        }

        return $Conf->getSection('bankAccounts');
    }
}
