<?php

namespace App\Services;

use Doctrine\DBAL\Connection;

class Clickhouse
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function showDatabases()
    {
        $sql = 'SHOW DATABASES';
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    public function showTables()
    {
        $sql = 'SHOW TABLES FROM test';
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    public function insertIntoTable(string $table, array $values)
    {
        $temp = [];
        $keys = array_keys($values[0] ?? []);
        $keys = implode(',', $keys);
        foreach ($values as $value) {
            $val = array_values($value);
            $val = array_map(function ($item) {
                $item = str_replace('\'', '', $item);

                return is_numeric($item) ? $item : "'$item'";
            }, $val);
            $temp[] = '('.implode(',', $val).')';
        }
        $temp = implode(',', $temp);
        $sql = "INSERT INTO test.$table ($keys) FORMAT Values $temp";
        $stmt = $this->connection->prepare($sql);
        $stmt->executeQuery();
    }

    public function selectTable(string $table)
    {
        $sql = "SELECT * FROM test.$table";
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }
}
