<?php

namespace QUI\ERP\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;

use function array_filter;
use function array_keys;
use function date_create;
use function explode;
use function is_array;
use function strtoupper;

/**
 * Builds and executes the manufacturer search independently of global QUI state.
 */
class ManufacturerSearch
{
    /**
     * @param int[] $manufacturerGroupIds
     * @param array<mixed> $searchParams
     * @param array<mixed> $gridParams
     * @return array<int, array<string, mixed>>|int
     * @throws DbalException
     */
    public static function execute(
        Connection $Connection,
        string $usersTable,
        string $usersAddressTable,
        array $manufacturerGroupIds,
        array $searchParams,
        array $gridParams,
        bool $countOnly = false
    ): array|int {
        $QueryBuilder = $Connection->createQueryBuilder();
        $Platform = $Connection->getDatabasePlatform();
        $quote = static fn(string $identifier): string => $Platform->quoteSingleIdentifier($identifier);
        $column = static fn(string $alias, string $name): string => $alias . '.' . $quote($name);

        if ($countOnly) {
            $QueryBuilder->select('COUNT(*)');
        } else {
            $QueryBuilder->select(
                $column('u', 'id'),
                $column('u', 'firstname'),
                $column('u', 'lastname'),
                $column('u', 'email'),
                $column('u', 'username'),
                $column('ua', 'company'),
                $column('u', 'usergroup'),
                $column('u', 'active'),
                $column('u', 'regdate')
            );
        }

        $QueryBuilder
            ->from($quote($usersTable), 'u')
            ->leftJoin(
                'u',
                $quote($usersAddressTable),
                'ua',
                $column('u', 'address') . ' = ' . $column('ua', 'id')
            );

        $groupExpressions = [];

        foreach ($manufacturerGroupIds as $index => $groupId) {
            $parameter = 'group' . $index;
            $groupExpressions[] = $QueryBuilder->expr()->like($column('u', 'usergroup'), ':' . $parameter);
            $QueryBuilder->setParameter($parameter, '%,' . $groupId . ',%', ParameterType::STRING);
        }

        if (!empty($groupExpressions)) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$groupExpressions));
        }

        $searchFields = ['id', 'username', 'email', 'company'];

        if (!empty($searchParams['filter']) && is_array($searchParams['filter'])) {
            $searchFields = array_keys(array_filter(
                $searchParams['filter'],
                static fn(mixed $value): bool => (bool)(int)$value
            ));

            if (!empty($searchParams['filter']['regdate_from'])) {
                $DateFrom = date_create($searchParams['filter']['regdate_from']);

                if ($DateFrom) {
                    $DateFrom->setTime(0, 0, 0);
                    $QueryBuilder->andWhere(
                        $QueryBuilder->expr()->gte($column('u', 'regdate'), ':dateFrom')
                    );
                    $QueryBuilder->setParameter('dateFrom', $DateFrom->getTimestamp(), ParameterType::INTEGER);
                }
            }

            if (!empty($searchParams['filter']['regdate_to'])) {
                $DateTo = date_create($searchParams['filter']['regdate_to']);

                if ($DateTo) {
                    $DateTo->setTime(23, 59, 59);
                    $QueryBuilder->andWhere(
                        $QueryBuilder->expr()->lte($column('u', 'regdate'), ':dateTo')
                    );
                    $QueryBuilder->setParameter('dateTo', $DateTo->getTimestamp(), ParameterType::INTEGER);
                }
            }
        }

        if (!empty($searchParams['search'])) {
            $searchExpressions = [];

            foreach ($searchFields as $filter) {
                switch ($filter) {
                    case 'id':
                    case 'username':
                    case 'firstname':
                    case 'lastname':
                    case 'email':
                        $searchExpressions[] = $QueryBuilder->expr()->like($column('u', $filter), ':search');
                        break;

                    case 'company':
                        $searchExpressions[] = $QueryBuilder->expr()->like($column('ua', $filter), ':search');
                        break;

                    default:
                        continue 2;
                }
            }

            if (!empty($searchExpressions)) {
                $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$searchExpressions));
                $QueryBuilder->setParameter(
                    'search',
                    '%' . (string)$searchParams['search'] . '%',
                    ParameterType::STRING
                );
            }
        }

        $sortColumns = [
            'id' => $column('u', 'id'),
            'username' => $column('u', 'username'),
            'firstname' => $column('u', 'firstname'),
            'lastname' => $column('u', 'lastname'),
            'email' => $column('u', 'email'),
            'company' => $column('ua', 'company')
        ];
        $sortOn = (string)($searchParams['sortOn'] ?? '');

        if (!$countOnly && isset($sortColumns[$sortOn])) {
            $sortBy = strtoupper((string)($searchParams['sortBy'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $QueryBuilder->orderBy($sortColumns[$sortOn], $sortBy);
        }

        if (!$countOnly) {
            if (!empty($gridParams['limit'])) {
                $limit = explode(',', (string)$gridParams['limit'], 2);

                if (isset($limit[1])) {
                    $QueryBuilder->setFirstResult((int)$limit[0]);
                    $QueryBuilder->setMaxResults((int)$limit[1]);
                } else {
                    $QueryBuilder->setMaxResults((int)$limit[0]);
                }
            } else {
                $QueryBuilder->setMaxResults(20);
            }
        }

        $Result = $QueryBuilder->executeQuery();

        if ($countOnly) {
            return (int)$Result->fetchOne();
        }

        return $Result->fetchAllAssociative();
    }
}
