<?php

namespace QUI\ERP\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

use function array_map;

/**
 * Small, testable collection of the shared ERP DBAL queries.
 */
class Queries
{
    /**
     * @return array<string, mixed>|false
     * @throws DbalException
     */
    public static function fetchAssociativeByIdentifier(
        Connection $Connection,
        string $table,
        string $identifier,
        string $value
    ): array|false {
        $Platform = $Connection->getDatabasePlatform();
        $quote = static fn(string $name): string => $Platform->quoteSingleIdentifier($name);

        return $Connection->createQueryBuilder()
            ->select('*')
            ->from($quote($table))
            ->where($quote($identifier) . ' = :identifierValue')
            ->setParameter('identifierValue', $value)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @param array<string, mixed> $data
     * @throws DbalException
     */
    public static function insert(Connection $Connection, string $table, array $data): int|string
    {
        return $Connection->insert($table, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $criteria
     * @throws DbalException
     */
    public static function update(
        Connection $Connection,
        string $table,
        array $data,
        array $criteria
    ): int|string {
        return $Connection->update($table, $data, $criteria);
    }

    /**
     * @param array<string> $columns
     * @return array<array<string, mixed>>
     * @throws DbalException
     */
    public static function fetchAllAssociative(
        Connection $Connection,
        string $table,
        array $columns
    ): array {
        $Platform = $Connection->getDatabasePlatform();
        $quote = static fn(string $identifier): string => $Platform->quoteSingleIdentifier($identifier);

        return $Connection->createQueryBuilder()
            ->select(...array_map($quote, $columns))
            ->from($quote($table))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Fetches rows if either of the two identifiers matches the value.
     *
     * @param array<string> $columns
     * @return array<array<string, mixed>>
     * @throws DbalException
     */
    public static function fetchAllAssociativeByEitherIdentifier(
        Connection $Connection,
        string $table,
        array $columns,
        string $firstIdentifier,
        string $secondIdentifier,
        string $value
    ): array {
        $QueryBuilder = $Connection->createQueryBuilder();
        $Platform = $Connection->getDatabasePlatform();
        $quote = static fn(string $identifier): string => $Platform->quoteSingleIdentifier($identifier);

        return $QueryBuilder
            ->select(...array_map($quote, $columns))
            ->from($quote($table))
            ->where($QueryBuilder->expr()->or(
                $QueryBuilder->expr()->eq($quote($firstIdentifier), ':identifierValue'),
                $QueryBuilder->expr()->eq($quote($secondIdentifier), ':identifierValue')
            ))
            ->setParameter('identifierValue', $value)
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
