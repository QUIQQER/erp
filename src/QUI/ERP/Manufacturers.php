<?php

namespace QUI\ERP;

use Doctrine\DBAL\Exception as DbalException;
use IntlDateFormatter;
use QUI;
use QUI\ERP\Database\ManufacturerSearch;
use QUI\ERP\Products\Handler\Fields;
use QUI\Utils\Security\Orthos;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function array_walk;
use function count;
use function implode;
use function in_array;
use function is_array;
use function sort;
use function str_replace;
use function trim;

/**
 * Class Manufacturers
 *
 * Main handler for manufacturer management
 */
class Manufacturers
{
    /**
     * Get all group IDs that are assigned to the manufacturer product field
     *
     * @return int[]
     */
    public static function getManufacturerGroupIds(): array
    {
        $groupIds = [];

        try {
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
            $defaultGroupId = $Conf?->get('manufacturers', 'groupId');

            if (!empty($defaultGroupId)) {
                $groupIds[] = (int)$defaultGroupId;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        if (!QUI::getPackageManager()->isInstalled('quiqqer/products')) {
            return $groupIds;
        }

        // If quiqqer/products is installed also check groups of default product field "Manufacturer"
        try {
            $ManufacturerField = Fields::getField(Fields::FIELD_MANUFACTURER);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $groupIds;
        }

        $fieldGroupIds = $ManufacturerField->getOption('groupIds');

        if (empty($fieldGroupIds) || !is_array($fieldGroupIds)) {
            return $groupIds;
        }

        array_walk($fieldGroupIds, function (&$groupId) {
            $groupId = (int)$groupId;
        });

        $groupIds = array_merge($groupIds, $fieldGroupIds);

        return array_values(array_unique($groupIds));
    }

    /**
     * Create a new manufacturer user
     *
     * @param string $manufacturerId - QUIQQER username
     * @param array<mixed> $address
     * @param array<mixed> $groupIds - QUIQQER group IDs of manufacturer groups
     *
     * @return QUI\Interfaces\Users\User
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public static function createManufacturer(
        string $manufacturerId,
        array $address = [],
        array $groupIds = []
    ): QUI\Interfaces\Users\User {
        QUI\Permissions\Permission::checkPermission('quiqqer.erp_manufacturers.create');

        $Users = QUI::getUsers();
        $manufacturerId = $Users::clearUsername($manufacturerId);

        // Check ID
        if ($Users->usernameExists($manufacturerId)) {
            throw new Exception([
                'quiqqer/erp',
                'exception.Manufacturers.createManufacturer.id_already_exists',
                [
                    'userId' => $manufacturerId
                ]
            ]);
        }

        $SystemUser = $Users->getSystemUser();
        $User = $Users->createChild($manufacturerId, $SystemUser);

        if (!empty($address)) {
            try {
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception) {
                $Address = $User->addAddress();
            }

            if ($Address === null) {
                throw new QUI\Exception('Could not create manufacturer address');
            }

            $needles = [
                'salutation',
                'firstname',
                'lastname',
                'company',
                'delivery',
                'street_no',
                'zip',
                'city',
                'country'
            ];

            foreach ($needles as $needle) {
                if (!isset($address[$needle])) {
                    $address[$needle] = '';
                }
            }

            $Address->setAttribute('salutation', $address['salutation']);
            $Address->setAttribute('firstname', $address['firstname']);
            $Address->setAttribute('lastname', $address['lastname']);
            $Address->setAttribute('company', $address['company']);
            $Address->setAttribute('delivery', $address['delivery']);
            $Address->setAttribute('street_no', $address['street_no']);
            $Address->setAttribute('zip', $address['zip']);
            $Address->setAttribute('city', $address['city']);
            $Address->setAttribute('country', $address['country']);

            // E-Mail
            if (!empty($address['email']) && Orthos::checkMailSyntax($address['email'])) {
                $User->setAttribute('email', $address['email']);
                $Address->addMail($address['email']);
            }

            $Address->save();

            if (empty($User->getAttribute('firstname'))) {
                $User->setAttribute('firstname', $address['firstname']);
            }

            if (empty($User->getAttribute('lastname'))) {
                $User->setAttribute('lastname', $address['lastname']);
            }
        }

        // groups
        $manufacturerGroupIds = self::getManufacturerGroupIds();

        foreach ($groupIds as $groupId) {
            $groupId = (int)$groupId;

            if (in_array($groupId, $manufacturerGroupIds)) {
                $User->addToGroup($groupId);
            }
        }

        $User->save($SystemUser);

        // Set random password and activate
        $User->setPassword(QUI\Security\Password::generateRandom(), $SystemUser);
        $User->activate('', $SystemUser);

        return $User;
    }

    /**
     * Search manufacturers
     *
     * @param array<mixed> $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return array<int, array<string, mixed>>|int - Manufacturer data or count
     */
    public static function search(array $searchParams, bool $countOnly = false): array|int
    {
        $Grid = new QUI\Utils\Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        try {
            return ManufacturerSearch::execute(
                QUI::getDataBaseConnection(),
                QUI::getDBTableName('users'),
                QUI::getDBTableName('users_address'),
                self::getManufacturerGroupIds(),
                $searchParams,
                $gridParams,
                $countOnly
            );
        } catch (DbalException $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }
    }

    /**
     * Parse data and prepare for frontend use with GRID
     *
     * @param array<mixed> $data - Search result IDs
     * @return array<mixed>
     */
    public static function parseListForGrid(array $data): array
    {
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        $DateFormatterLong = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::SHORT
        );

        $result = [];
        $Groups = QUI::getGroups();
        $Users = QUI::getUsers();

        foreach ($data as $entry) {
            $entry['usergroup'] = trim($entry['usergroup'], ',');
            $entry['usergroup'] = explode(',', $entry['usergroup']);
            $entry['usergroup'] = array_map(function ($groupId) {
                return (int)$groupId;
            }, $entry['usergroup']);

            $groups = array_map(function ($groupId) use ($Groups) {
                try {
                    $Group = $Groups->get($groupId);

                    return $Group->getName();
                } catch (QUI\Exception) {
                }

                return '';
            }, $entry['usergroup']);

            sort($groups);
            $groups = implode(', ', $groups);
            $groups = str_replace(',,', '', $groups);
            $groups = trim($groups, ',');

            $addressData = [];
            $Address = null;

            try {
                $User = $Users->get($entry['id']);
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception) {
            }

            if ($Address && (empty($entry['firstname']) || empty($entry['lastname']))) {
                $name = [];

                if ($Address->getAttribute('firstname')) {
                    $entry['firstname'] = $Address->getAttribute('firstname');
                    $name[] = $Address->getAttribute('firstname');
                }

                if ($Address->getAttribute('lastname')) {
                    $entry['lastname'] = $Address->getAttribute('lastname');
                    $name[] = $Address->getAttribute('lastname');
                }

                if (!empty($name)) {
                    $addressData[] = implode(' ', $name);
                }
            }

            if ($Address) {
                $addressData[] = $Address->getText();

                if (empty($entry['email'])) {
                    $mails = $Address->getMailList();

                    if (count($mails)) {
                        $entry['email'] = $mails[0];
                    }
                }

                if (empty($entry['company'])) {
                    $entry['company'] = $Address->getAttribute('company');
                }
            }

            $result[] = [
                'id' => (int)$entry['id'],
                'status' => !!$entry['active'],
                'username' => $entry['username'],
                'firstname' => $entry['firstname'],
                'lastname' => $entry['lastname'],
                'company' => $entry['company'],
                'email' => $entry['email'],
                'regdate' => $DateFormatterLong->format($entry['regdate']),

                'usergroup_display' => $groups,
                'usergroup' => $entry['usergroup'],
                'address_display' => implode(' - ', $addressData)
            ];
        }

        return $result;
    }
}
