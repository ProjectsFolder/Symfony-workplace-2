<?php

namespace App\Services;

use Doctrine\DBAL\Connection;

class BGBilling
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function execSQL(string $sql, array $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery($params);

        return $result->fetchAllAssociative();
    }
}
