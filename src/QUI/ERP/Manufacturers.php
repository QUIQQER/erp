<?php

namespace QUI\ERP;

use IntlDateFormatter;
use PDO;
use QUI;
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
use function date_create;
use function explode;
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
            $defaultGroupId = $Conf->get('manufacturers', 'groupId');

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
        /** @var QUI\ERP\Products\Field\Types\GroupList $ManufacturerField */
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
     * @param array $address
     * @param array $groupIds - QUIQQER group IDs of manufacturer groups
     *
     * @return QUI\Users\User
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public static function createManufacturer(
        string $manufacturerId,
        array $address = [],
        array $groupIds = []
    ): QUI\Users\User {
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

            if (!$User->getAttribute('firstname') || $User->getAttribute('firstname') === '') {
                $User->setAttribute('firstname', $address['firstname']);
            }

            if (!$User->getAttribute('lastname') || $User->getAttribute('lastname') === '') {
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
        $User->activate(false, $SystemUser);

        return $User;
    }

    /**
     * Search manufacturers
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return int[]|int - Manufacturer user IDs or count
     */
    public static function search(array $searchParams, bool $countOnly = false)
    {
        $Grid = new QUI\Utils\Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);
        $usersTbl = QUI::getDBTableName('users');
        $usersAddressTbl = QUI::getDBTableName('users_address');
        $binds = [];
        $where = [];

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT u.`id`, u.`firstname`, u.`lastname`, u.`email`, u.`username`, ua.`company`, u.`usergroup`";
            $sql .= ", u.`active`, u.`regdate`";
        }

        $sql .= " FROM `" . $usersTbl . "` as u LEFT JOIN `" . $usersAddressTbl . "` as ua ON u.`address` = ua.`id`";

        // Only fetch users in manufacturer groups
        $gc = 0;
        $whereOr = [];

        foreach (self::getManufacturerGroupIds() as $groupId) {
            $whereOr[] = "u.`usergroup` LIKE :group" . $gc;
            $bind = 'group' . $gc++;

            $binds[$bind] = [
                'value' => '%,' . $groupId . ',%',
                'type' => PDO::PARAM_STR
            ];
        }

        if (!empty($whereOr)) {
            $where[] = "(" . implode(" OR ", $whereOr) . ")";
        }

        // User search
        $searchFields = [
            'id',
            'username',
            'email',
            'company'
        ];

        if (!empty($searchParams['filter']) && is_array($searchParams['filter'])) {
            $searchFields = array_filter($searchParams['filter'], function ($value) {
                return !!(int)$value;
            });

            $searchFields = array_keys($searchFields);

            // date filters
            if (!empty($searchParams['filter']['regdate_from'])) {
                $DateFrom = date_create($searchParams['filter']['regdate_from']);

                if ($DateFrom) {
                    $DateFrom->setTime(0, 0, 0);

                    $bind = 'datefrom';
                    $where[] = 'u.`regdate` >= :' . $bind;

                    $binds[$bind] = [
                        'value' => $DateFrom->getTimestamp(),
                        'type' => PDO::PARAM_INT
                    ];
                }
            }

            if (!empty($searchParams['filter']['regdate_to'])) {
                $DateTo = date_create($searchParams['filter']['regdate_to']);

                if ($DateTo) {
                    $DateTo->setTime(23, 59, 59);

                    $bind = 'dateto';
                    $where[] = 'u.`regdate` <= :' . $bind;

                    $binds[$bind] = [
                        'value' => $DateTo->getTimestamp(),
                        'type' => PDO::PARAM_INT
                    ];
                }
            }
        }

        if (!empty($searchParams['search'])) {
            $searchValue = $searchParams['search'];
            $fc = 0;
            $whereOr = [];

            // search value filters
            foreach ($searchFields as $filter) {
                $bind = 'filter' . $fc;

                switch ($filter) {
                    case 'id':
                    case 'username':
                    case 'firstname':
                    case 'lastname':
                    case 'email':
                        $whereOr[] = 'u.`' . $filter . '` LIKE :' . $bind;
                        break;

                    case 'company':
                        $whereOr[] = 'ua.`' . $filter . '` LIKE :' . $bind;
                        break;

                    default:
                        continue 2;
                }

                $binds[$bind] = [
                    'value' => '%' . $searchValue . '%',
                    'type' => PDO::PARAM_STR
                ];

                $fc++;
            }

            if (!empty($whereOr)) {
                $where[] = "(" . implode(" OR ", $whereOr) . ")";
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ORDER
        if (!empty($searchParams['sortOn'])) {
            $sortOn = Orthos::clear($searchParams['sortOn']);

            switch ($sortOn) {
                case 'id':
                case 'username':
                case 'firstname':
                case 'lastname':
                case 'email':
                    $sortOn = 'u.`' . $sortOn . '`';
                    break;

                case 'company':
                    $sortOn = 'ua.`' . $sortOn . '`';
                    break;
            }

            $order = "ORDER BY " . $sortOn;

            if (!empty($searchParams['sortBy'])) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " " . $order;
        }

        // LIMIT
        if (!empty($gridParams['limit']) && !$countOnly) {
            $sql .= " LIMIT " . $gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT " . (int)20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        if ($countOnly) {
            return (int)current(current($result));
        }

        return $result;
    }

    /**
     * Parse data and prepare for frontend use with GRID
     *
     * @param array $data - Search result IDs
     * @return array
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
