<?php

namespace PimEnterprise\Bundle\ClassificationRuleBundle\Engine\ProductRuleApplier;

use Akeneo\Bundle\RuleEngineBundle\Model\RuleInterface;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityNotFoundException;
use Pim\Bundle\CatalogBundle\Model\CategoryInterface;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Repository\CategoryRepositoryInterface;
use Pim\Bundle\CatalogBundle\Updater\ProductTemplateUpdaterInterface;
use Pim\Bundle\CatalogBundle\Updater\ProductUpdaterInterface;
use PimEnterprise\Bundle\ClassificationRuleBundle\Model\ProductAddCategoryActionInterface;
use PimEnterprise\Bundle\ClassificationRuleBundle\Model\ProductSetCategoryActionInterface;
use PimEnterprise\Bundle\CatalogRuleBundle\Engine\ProductRuleApplier\ProductsUpdater as BaseProductsUpdater;
use PimEnterprise\Bundle\CatalogRuleBundle\Model\ProductCopyValueActionInterface;
use PimEnterprise\Bundle\CatalogRuleBundle\Model\ProductSetValueActionInterface;

/**
 * Saves products when apply a rule.
 *
 * @author    Damien Carcel <damien.carcel@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductsUpdater extends BaseProductsUpdater
{
    /** @var CategoryRepositoryInterface */
    protected $categoryRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        ProductUpdaterInterface $productUpdater,
        ProductTemplateUpdaterInterface $templateUpdater,
        CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct($productUpdater, $templateUpdater);

        $this->categoryRepository = $categoryRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function updateFromRule(array $products, RuleInterface $rule)
    {
        $actions = $rule->getActions();
        foreach ($actions as $action) {
            if ($action instanceof ProductSetValueActionInterface) {
                $this->applySetAction($products, $action);
            } elseif ($action instanceof ProductCopyValueActionInterface) {
                $this->applyCopyAction($products, $action);
            } elseif ($action instanceof ProductAddCategoryActionInterface) {
                $this->applyAddCategoryAction($products, $action);
            } elseif ($action instanceof ProductSetCategoryActionInterface) {
                $this->applySetCategoryAction($products, $action);
            } else {
                throw new \LogicException(
                    sprintf('The action "%s" is not supported yet.', ClassUtils::getClass($action))
                );
            }
        }
    }

    /**
     * Applies a add category action on a subject set, if this category exists.
     *
     * @param ProductInterface[]                $products
     * @param ProductAddCategoryActionInterface $action
     *
     * @return ProductsUpdater
     */
    protected function applyAddCategoryAction(array $products, ProductAddCategoryActionInterface $action)
    {
        $category = $this->getCategory($action->getCategoryCode());
        foreach ($products as $product) {
            $product->addCategory($category);
        }

        return $this;
    }

    /**
     * Applies a set category action on a subject set, if this category exists.
     *
     * @param ProductInterface[]                $products
     * @param ProductSetCategoryActionInterface $action
     *
     * @return ProductsUpdater
     */
    protected function applySetCategoryAction(array $products, ProductSetCategoryActionInterface $action)
    {
        $category = $this->getCategory($action->getCategoryCode());
        $tree     = ($action->getTreeCode()) ? $this->getCategory($action->getTreeCode()) : null;

        foreach ($products as $product) {
            // Remove categories (only a tree if asked) from the product
            $categories = $product->getCategories();
            foreach ($categories as $category) {
                if (null === $tree) {
                    $product->removeCategory($category);
                } else if ($category->getRoot() === $tree->getId()) {
                    $product->removeCategory($category);
                }
            }

            $product->addCategory($category);
        }

        return $this;
    }

    /**
     * @param string $categoryCode
     *
     * @return CategoryInterface
     *
     * @throws \Exception
     */
    protected function getCategory($categoryCode)
    {
        $category = $this->categoryRepository->findOneByIdentifier($categoryCode);
        if (null !== $category) {
            throw new EntityNotFoundException(
                sprintf(
                    'Impossible to apply rule to on this category cause the category "%s" does not exist',
                    $categoryCode
                )
            );
        }

        return $category;
    }
}
