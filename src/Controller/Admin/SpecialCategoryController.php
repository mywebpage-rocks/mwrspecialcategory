<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 */

namespace PrestaShop\Module\mwrspecialcategory\Controller\Admin;

use PrestaShop\Module\mwrspecialcategory\Exception\CannotCreateSpecialCategoryException;
use PrestaShop\Module\mwrspecialcategory\Exception\CannotToggleIsSpecialCategoryStatusException;
use PrestaShop\Module\mwrspecialcategory\Exception\SpecialCategoryException;
use PrestaShop\Module\mwrspecialcategory\Entity\SpecialCategory;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerException;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * This controller holds all custom actions which are added by extending "Sell > Customers" page.
 *
 * @see https://devdocs.prestashop.com/1.7/modules/concepts/controllers/admin-controllers/ for more details.
 */
class SpecialCategoryController extends FrameworkBundleAdminController
{
    /**
     * Catches the toggle action of customer review.
     *
     * @param int $categoryId
     *
     * @return RedirectResponse
     */
    public function toggleIsSpecialCategoryAction(int $categoryId)
    {
        try {
            $specialCategoryId = $this->get('mwrspecialcategory.repository.specialCategory')->findIdByCategory($categoryId);

            $specialCategory = new SpecialCategory((int) $specialCategoryId);
            if (0 >= $specialCategory->id) {
                $specialCategory = $this->createModifierIfNeeded($categoryId);
            }
            $specialCategory->is_special_category = (bool) !$specialCategory->is_special_category;

            try {
                if (false === $specialCategory->update()) {
                    throw new CannotToggleIsSpecialCategoryStatusException(
                        sprintf('Failed to change status for specialCategory with id "%s"', $specialCategory->id)
                    );
                }
            } catch (\PrestaShopException $exception) {
                throw new CannotToggleIsSpecialCategoryStatusException(
                    'An unexpected error occurred when updating specialCategory status'
                );
            }

            $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));
        } catch (SpecialCategoryException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessageMapping()));
        }

        return $this->redirectToRoute('admin_categories_index');
    }

    /**
     * Gets error message mappings which are later used to display friendly user error message instead of the
     * exception message.
     *
     * @return array
     */
    private function getErrorMessageMapping()
    {
        return [
            CustomerException::class => $this->trans(
                'Something bad happened when trying to get customer id',
                'Modules.mwrspecialcategory.Customerreviewcontroller'
            ),
            CannotCreateSpecialCategoryException::class => $this->trans(
                'Failed to create specialCategory',
                'Modules.mwrspecialcategory.Customerreviewcontroller'
            ),
            CannotToggleSpecialCategoryStatusException::class => $this->trans(
                'An error occurred while updating the status.',
                'Modules.mwrspecialcategory.Customerreviewcontroller'
            ),
        ];
    }

    /**
     * Creates a specialCategory. Used when toggle action is used on customer whose data is empty.
     *
     * @param int $categoryId
     *
     * @return SpecialCategory
     *
     * @throws CannotCreateSpecialCategoryException
     */
    protected function createModifierIfNeeded(int $categoryId)
    {
        try {
            $specialCategory = new SpecialCategory();
            $specialCategory->id_category = $categoryId;
            $specialCategory->is_special_category = 0;

            if (false === $specialCategory->save()) {
                throw new CannotCreateSpecialCategoryException(
                    sprintf(
                        'An error occurred when creating specialCategory with customer id "%s"',
                        $categoryId
                    )
                );
            }
        } catch (\PrestaShopException $exception) {
            throw new CannotCreateSpecialCategoryException(
                sprintf(
                    'An unexpected error occurred when creating specialCategory with customer id "%s"',
                    $categoryId
                ),
                0,
                $exception
            );
        }

        return $specialCategory;
    }
}