<?php

namespace QUI\ERP;

use QUI;
use QUI\ERP\Products\Handler\Fields;
use QUI\Utils\Security\Orthos;

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
    public static function getManufacturerGroupIds()
    {
        /** @var QUI\ERP\Products\Field\Types\GroupList $ManufacturerField */
        try {
            $ManufacturerField = Fields::getField(Fields::FIELD_MANUFACTURER);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        $groupIds = $ManufacturerField->getOption('groupIds');

        if (empty($groupIds) || !\is_array($groupIds)) {
            return [];
        }

        \array_walk($groupIds, function (&$groupId) {
            $groupId = (int)$groupId;
        });

        return $groupIds;
    }

    /**
     * Search manufacturers
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return int[]|int - membership user IDs or count
     */
    public static function search(array $searchParams, $countOnly = false)
    {
        $Grid            = new QUI\Utils\Grid($searchParams);
        $gridParams      = $Grid->parseDBParams($searchParams);
        $usersTbl        = QUI::getDBTableName('users');
        $usersAddressTbl = QUI::getDBTableName('users_address');
        $binds           = [];
        $where           = [];

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT u.`id`, u.`firstname`, u.`lastname`, u.`email`, u.`username`, ua.`company`, u.`usergroup`";
            $sql .= ", u.`active`, u.`regdate`";
        }

        $sql .= " FROM `".$usersTbl."` as u LEFT JOIN `".$usersAddressTbl."` as ua ON u.`address` = ua.`id`";

        // Only fetch users in manufacturer groups
        $gc      = 0;
        $whereOr = [];

        foreach (self::getManufacturerGroupIds() as $groupId) {
            $whereOr[] = "u.`usergroup` LIKE :group".$gc;
            $bind      = 'group'.$gc;

            $binds[$bind] = [
                'value' => '%,'.$groupId.',%',
                'type'  => \PDO::PARAM_STR
            ];
        }

        if (!empty($whereOr)) {
            $where[] = "(".\implode(" OR ", $whereOr).")";
        }

        // User search
        $searchFields = [
            'id',
            'username',
            'email',
            'company'
        ];

        if (!empty($searchParams['filter']) && \is_array($searchParams['filter'])) {
            $searchFields = \array_filter($searchParams['filter'], function ($value) {
                return !!(int)$value;
            });

            $searchFields = \array_keys($searchFields);

            // date filters
            if (!empty($searchParams['filter']['regdate_from'])) {
                $DateFrom = \date_create($searchParams['filter']['regdate_from']);

                if ($DateFrom) {
                    $DateFrom->setTime(0, 0, 0);

                    $bind    = 'datefrom';
                    $where[] = 'u.`regdate` >= :'.$bind;

                    $binds[$bind] = [
                        'value' => $DateFrom->getTimestamp(),
                        'type'  => \PDO::PARAM_INT
                    ];
                }
            }

            if (!empty($searchParams['filter']['regdate_to'])) {
                $DateTo = \date_create($searchParams['filter']['regdate_to']);

                if ($DateTo) {
                    $DateTo->setTime(23, 59, 59);

                    $bind    = 'dateto';
                    $where[] = 'u.`regdate` <= :'.$bind;

                    $binds[$bind] = [
                        'value' => $DateTo->getTimestamp(),
                        'type'  => \PDO::PARAM_INT
                    ];
                }
            }
        }

        if (!empty($searchParams['search'])) {
            $searchValue = $searchParams['search'];
            $fc          = 0;
            $whereOr     = [];

            // search value filters
            foreach ($searchFields as $filter) {
                $bind = 'filter'.$fc;

                switch ($filter) {
                    case 'id':
                    case 'username':
                    case 'firstname':
                    case 'lastname':
                    case 'email':
                        $whereOr[] = 'u.`'.$filter.'` LIKE :'.$bind;
                        break;

                    case 'company':
                        $whereOr[] = 'ua.`'.$filter.'` LIKE :'.$bind;
                        break;

                    default:
                        continue 2;
                }

                $binds[$bind] = [
                    'value' => '%'.$searchValue.'%',
                    'type'  => \PDO::PARAM_STR
                ];

                $fc++;
            }

            if (!empty($whereOr)) {
                $where[] = "(".\implode(" OR ", $whereOr).")";
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE ".implode(" AND ", $where);
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
                    $sortOn = 'u.`'.$sortOn.'`';
                    break;

                case 'company':
                    $sortOn = 'ua.`'.$sortOn.'`';
                    break;
            }

            $order = "ORDER BY ".$sortOn;

            if (!empty($searchParams['sortBy'])) {
                $order .= " ".Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " ".$order;
        }

        // LIMIT
        if (!empty($gridParams['limit']) && !$countOnly) {
            $sql .= " LIMIT ".$gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT ".(int)20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':'.$var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
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
    public static function parseListForGrid(array $data)
    {
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        $DateFormatterLong = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::SHORT
        );

        $result = [];
        $Groups = QUI::getGroups();
        $Users  = QUI::getUsers();

        foreach ($data as $entry) {
            $entry['usergroup'] = \trim($entry['usergroup'], ',');
            $entry['usergroup'] = \explode(',', $entry['usergroup']);
            $entry['usergroup'] = \array_map(function ($groupId) {
                return (int)$groupId;
            }, $entry['usergroup']);

            $groups = \array_map(function ($groupId) use ($Groups) {
                try {
                    $Group = $Groups->get($groupId);

                    return $Group->getName();
                } catch (QUI\Exception $Exception) {
                }

                return '';
            }, $entry['usergroup']);

            \sort($groups);
            $groups = \implode(', ', $groups);
            $groups = \str_replace(',,', '', $groups);
            $groups = \trim($groups, ',');

            $addressData = [];
            $Address     = null;

            try {
                $User    = $Users->get((int)$entry['id']);
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception $Exception) {
            }

            if ($Address && (empty($entry['firstname']) || empty($entry['lastname']))) {
                $name = [];

                if ($Address->getAttribute('firstname')) {
                    $entry['firstname'] = $Address->getAttribute('firstname');
                    $name[]             = $Address->getAttribute('firstname');
                }

                if ($Address->getAttribute('lastname')) {
                    $entry['lastname'] = $Address->getAttribute('lastname');
                    $name[]            = $Address->getAttribute('lastname');
                }

                if (!empty($name)) {
                    $addressData[] = \implode(' ', $name);
                }
            }

            if ($Address) {
                $addressData[] = $Address->getText();

                if (empty($entry['email'])) {
                    $mails = $Address->getMailList();

                    if (\count($mails)) {
                        $entry['email'] = $mails[0];
                    }
                }

                if (empty($entry['company'])) {
                    $entry['company'] = $Address->getAttribute('company');
                }
            }

            $result[] = [
                'id'        => (int)$entry['id'],
                'status'    => !!$entry['active'],
                'username'  => $entry['username'],
                'firstname' => $entry['firstname'],
                'lastname'  => $entry['lastname'],
                'company'   => $entry['company'],
                'email'     => $entry['email'],
                'regdate'   => $DateFormatterLong->format($entry['regdate']),

                'usergroup_display' => $groups,
                'usergroup'         => $entry['usergroup'],
                'address_display'   => \implode(' - ', $addressData)
            ];
        }

        return $result;
    }
}
