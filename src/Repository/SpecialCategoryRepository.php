<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 */

namespace PrestaShop\Module\mwrspecialcategory\Repository;

use Doctrine\DBAL\Connection;
use PDO;

class SpecialCategoryRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $dbPrefix;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     */
    public function __construct(Connection $connection, $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * Finds customer id if such exists.
     *
     * @param int $categoryId
     *
     * @return int
     */
    public function findIdByCategory($categoryId)
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('`id_modifier`')
            ->from($this->dbPrefix . 'mwrspecialcategory')
            ->where('`id_category` = :category_id')
        ;

        $queryBuilder->setParameter('category_id', $categoryId);

        return (int) $queryBuilder->execute()->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Gets allowed to review status by customer.
     *
     * @param int $categoryId
     *
     * @return bool
     */
    public function getIsSpecialCategoryStatus($categoryId)
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('`is_special_category`')
            ->from($this->dbPrefix . 'mwrspecialcategory')
            ->where('`id_category` = :category_id')
        ;

        $queryBuilder->setParameter('category_id', $categoryId);

        return (bool) $queryBuilder->execute()->fetch(PDO::FETCH_COLUMN);
    }
}