<?php

namespace Shopware\Bundle\SearchBundle\DBAL\SortingHandler;

use Shopware\Bundle\SearchBundle\DBAL\SortingHandlerInterface;
use Shopware\Bundle\SearchBundle\Sorting\ReleaseDateSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Context;
use Shopware\Bundle\SearchBundle\DBAL\QueryBuilder;

/**
 * @category  Shopware
 * @package   Shopware\Bundle\SearchBundle\DBAL\SortingHandler
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ReleaseDateSortingHandler implements SortingHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supportsSorting(SortingInterface $sorting)
    {
        return ($sorting instanceof ReleaseDateSorting);
    }

    /**
     * Handles the passed sorting object.
     * Extends the passed query builder with the specify sorting.
     * Should use the addOrderBy function, otherwise other sortings would be overwritten.
     *
     * @param SortingInterface|ReleaseDateSorting $sorting
     * @param QueryBuilder $query
     * @param Context $context
     * @return void
     */
    public function generateSorting(
        SortingInterface $sorting,
        QueryBuilder $query,
        Context $context
    ) {
        $query->addOrderBy('product.datum', $sorting->getDirection())
            ->addOrderBy('product.changetime', $sorting->getDirection())
            ->addOrderBy('product.id', $sorting->getDirection());
    }

}
