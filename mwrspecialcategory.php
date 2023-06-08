<?php

declare(strict_types=1);

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\mwrspecialcategory\Entity\SpecialCategory;
use PrestaShop\Module\mwrspecialcategory\Exception\CannotCreateSpecialCategoryException;
use PrestaShop\Module\mwrspecialcategory\Exception\CannotToggleIsSpecialCategoryStatusException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryException;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Search\Filters\CategoryFilters;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class mwrspecialcategory extends Module
{
    protected $config_form = false;
    protected $fields_list = [];
    protected $field_values = [];
    protected $config_name;
    protected $is_PS_17;
    protected $ps_17_hooks;
    protected $ps_16_hooks;
    protected $hooks_list;
    protected $_full_path;

    public function __construct()
    {
        $this->name = 'mwrspecialcategory';
        $this->config_name = 'MWRSPECIALCATEGORY';
        $this->tab = 'merchandizing';
        $this->version = '1.0.0';
        $this->author = 'mywebpage rocks';
        $this->need_instance = 0;
        $this->is_PS_17 = (version_compare(_PS_VERSION_, '1.7.0.0', '>=') === true) ? true : false;
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('MWR Special Category');
        $this->description = $this->l('Zadanie 4');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module ?');
        $this->_full_path = dirname(__FILE__);
        $this->fields_list = [
            'ALLOWED_DOMAINS' => [
                'type' => 'text',
                'data' => 'float',
                'default_values' => [
                    'en' => 'x13.pl, mywebpage.rocks',
                    'pl' => 'x13.pl, mywebpage.rocks',
                ],
                'label' => $this->l('Allowed domains'),
                'col' => '4',
                'desc' => $this->l('Users registered outside these domains will be redirected to the CMS category selected below'),
                'required' => 'true',
                'icon' => 'money',
                'lang' => true
            ],
            'CMS_PAGE' => [
                'type'    => 'radio',
                'label'   => $this->l('CMS Pages'),
                'desc'    => $this->l('Select redirection CMS page'),
                'name'    => 'cms_pages',
                'class' => 't',
                'required' => true,
                'is_bool' => false
            ]
        ];

        $this->field_values = [
            'CMS_PAGE' => [
                'values' => $this->getCMSPages()
            ]
        ];
        foreach ($this->field_values as $key => $values) {
            $this->fields_list[$key] = array_merge($this->fields_list[$key], $values);
        }
        $this->hooks_list = [
            'actionCategoryGridDefinitionModifier',
            'actionCategoryGridQueryBuilderModifier',
            'actionCategoryFormBuilderModifier',
            'actionAfterCreateCategoryFormHandler',
            'actionAfterUpdateCategoryFormHandler'
        ];
        $this->ps_17_hooks = [];
        $this->ps_16_hooks = [];
        if ($this->is_PS_17) {
            $this->hooks_list = array_merge($this->hooks_list, $this->ps_17_hooks);
        } else {
            $this->hooks_list = array_merge($this->hooks_list, $this->ps_16_hooks);
        }
    }
    public function install()
    {
        return parent::install()
            && $this->installSql()
            && $this->setDefaultValues()
            && $this->registerHooks($this->hooks_list);
    }
    public function uninstall()
    {
        return $this->clearDefaultValues()
            && $this->unregisterHooks($this->hooks_list)
            && $this->uninstallSql()
            && parent::uninstall();
    }
    private function registerHooks($hooks_list = false)
    {
        if (!$hooks_list) {
            return true;
        }
        foreach ($this->hooks_list as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }
        return true;
    }
    private function unregisterHooks($hooks_list = false)
    {
        if (!$hooks_list) {
            return true;
        }
        foreach ($this->hooks_list as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                return true;
            }
            if (!$this->unregisterHook($hook)) {
                return false;
            }
        }
        return true;
    }
    public function getConfigName($value)
    {
        return $this->config_name . '_' . $value;
    }
    public function getOptionName($config_name)
    {
        return str_replace($this->config_name . '_', '', $config_name);
    }
    private function setDefaultValues()
    {
        $languages = Language::getLanguages(false);
        foreach ($this->fields_list as $key => $property) {
            if (isset($property['default_values']) && $property['default_values']) {
                foreach ($property['default_values'] as $iso => $value) {
                    foreach ($languages as $lang) {
                        if ($iso == $lang['iso_code']) {
                            Configuration::updateValue($this->getConfigName($key) . '_' . $lang['id_lang'], $value);
                        }
                    }
                }
            }
        }
        return true;
    }
    private function clearDefaultValues()
    {
        foreach ($this->fields_list as $key => $values) {
            Configuration::deleteByName($this->getConfigName($key));
        }
        return true;
    }
    private function installSql()
    {
        include($this->_full_path . '/sql/install.php');
        return true;
    }
    private function uninstallSql()
    {
        include($this->_full_path . '/sql/uninstall.php');
        return true;
    }
    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit' . $this->name)) {
            $this->postProcess();
        }

        return $output . $this->renderForm();
    }
    public function validateFormField($name)
    {
        // echo $name;
        // die();
        $value = Tools::getValue($name);
        $field = $this->fields_list[$name];
        if (isset($field['required']) && $field['required']) {
            if (!isset($value)) {
                return false;
            }
        } else if (empty($field['required']) && !$value) {
            return true;
        }
        if (isset($value)) {
            if (isset($field['type']) && ($field['type'] == 'int' || $field['type'] == 'float')) {
                if (!is_numeric($value)) {
                    return false;
                } else {
                    if ($field['type'] == 'int') {
                        if ((int)$value != $value) {
                            return false;
                        }
                        $value = (int)$value;
                    } else if ($field['type'] == 'float') {
                        if ((float)$value != $value) {
                            return false;
                        }
                        $value = (float)$value;
                        return false;
                    }
                    if (isset($field['min'])) {
                        if ($value < $field['min']) {
                            return false;
                        }
                    }
                    if (isset($field['max']) && !empty($field['max'])) {
                        if ($value > $field['max']) {
                            return false;
                        }
                    }
                    if (isset($field['not_zero']) && $field['not_zero']) {
                        if ($value === 0) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($this->getConfigForm()));
    }
    protected function getConfigForm()
    {
        $form = [];
        $form['legend'] = [
            'title' => $this->l('Basic settings'),
            'icon' => 'icon-cogs'
        ];
        foreach ($this->fields_list as $name => $field) {
            $form_field = [
                'name' => $this->getConfigName($name),
                'col' => $field['col'],
                'type' => $field['type'],
                'label' => $field['label'],
                'desc' => $field['desc'],
            ];
            if (isset($field['lang']) && $field['lang']) {
                $form_field['lang'] = $field['lang'];
            }
            if (isset($field['required']) && $field['required']) {
                $form_field['required'] = 'true';
            } else {
                $form_field['required'] = 'false';
            }
            if (isset($field['icon']) && $field['icon']) {
                $form_field['prefix'] = '<i class="icon icon-' . $field['icon'] . '"></i>';
            } else {
                $form_field['prefix'] = '<i class="icon icon-cogs"></i>';
            }
            if (isset($field['values']) && $field['values']) {
                $form_field['values'] = $field['values'];
            }
            $form['input'][] = $form_field;
        }

        $form['submit'] = [
            'title' => $this->l('Save configuration'),
            'class' => 'btn btn-default pull-right'
        ];
        $form['buttons'] = [];
        $config_form['form'] = $form;




        return $config_form;
    }
    protected function getCMSPages($amount = false)
    {
        $cms_pages = CMS::getCMSPages($this->context->language->id);
        $options = [];
        foreach ($cms_pages as $cms) {
            if ($cms['active']) {
                $option['id'] = $cms['meta_title'];
                $option['value'] = $cms['id_cms'];
                $option['label'] = $cms['meta_title'];
                $options[] = $option;
            }
        }

        return $options;
    }
    protected function getConfigFormValues()
    {
        $languages = Language::getLanguages(false);
        $form_values = [];
        foreach ($this->fields_list as $name => $field) {
            if ($field['type'] == 'radio' || $field['type'] == 'checkbox' || $field['type'] == 'select') {
                $form_values[$this->getConfigName($name)] = Configuration::get($this->getConfigName($name));
            }
            foreach ($languages as $lang) {
                $form_values[$this->getConfigName($name)][$lang['id_lang']] = Configuration::get($this->getConfigName($name) . '_' . $lang['id_lang']);
            }
        }
        return $form_values;
    }
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        $languages = Language::getLanguages(false);
        foreach ($form_values as $key => $field) {
            if (!(isset($this->fields_list[$this->getOptionName($key)]['lang']) && $this->fields_list[$this->getOptionName($key)]['lang'])) {
                Configuration::updateValue(($key), Tools::getValue($key));
            }
            foreach ($languages as $lang) {
                Configuration::updateValue(($key . '_' . $lang['id_lang']), Tools::getValue($key . '_' . $lang['id_lang']));
            }
        }
    }
    public function hookActionCategoryFormBuilderModifier(array $params)
    {
        /** @var FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $formBuilder->add('is_special_category', SwitchType::class, [
            'label' => $this->getTranslator()->trans('Special category', [], 'Modules.mwrspecialcategory.Admin'),
            'required' => false,
        ]);

        $result = false;
        if (null !== $params['id']) {
            $result = $this->get('mwrspecialcategory.repository.specialcategory')->getIsSpecialCategoryStatus((int) $params['id']);
        }

        $params['data']['is_special_category'] = $result;

        $formBuilder->setData($params['data']);
    }

    public function hookActionCategoryGridDefinitionModifier(array $params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];
        $definition
            ->getColumns()
            ->addAfter(
                'active',
                (new ToggleColumn('is_special_category'))
                    ->setName($this->l('Is Special'))
                    ->setOptions([
                        // 'field' => 'is_special_category',
                        // 'primary_field' => 'id_category',

                        'field' => 'is_special_category',
                        'primary_field' => 'id_category',
                        'route' => 'mwrspecialcategory_toggle_is_special_category', //toggleIsSpecialCategory
                        'route_param_name' => 'categoryId',
                    ])
            );
        // For search filter
        $definition->getFilters()->add(
            (new Filter('is_special_category', YesAndNoChoiceType::class))
                ->setAssociatedColumn('is_special_category')
        );
    }

    /**
     * Hook allows to modify Categories query builder and add custom sql statements.
     *
     * @param array $params
     */
    public function hookActionCategoryGridQueryBuilderModifier(array $params)
    {
        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        /** @var CategoryFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        $searchQueryBuilder->addSelect(
            'IF(mwr_cs.`is_special_category` IS NULL,0,mwr_cs.`is_special_category`) AS `is_special_category`'
        );

        $searchQueryBuilder->leftJoin(
            'c',
            '`' . pSQL(_DB_PREFIX_) . 'mwrspecialcategory`',
            'mwr_cs',
            'mwr_cs.`id_category` = c.`id_category`'
        );

        if ('is_special_category' === $searchCriteria->getOrderBy()) {
            $searchQueryBuilder->orderBy('mwr_cs.`is_special_category`', $searchCriteria->getOrderWay());
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('is_special_category' === $filterName) {
                $searchQueryBuilder->andWhere('mwr_cs.`is_special_category` = :is_special_category');
                $searchQueryBuilder->setParameter('is_special_category', $filterValue);

                if (!$filterValue) {
                    $searchQueryBuilder->orWhere('mwr_cs.`is_special_category` IS NULL');
                }
            }
        }
    }
    /**
     * Hook allows to modify Categories form and add additional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     *
     * @throws CategoryException
     */
    public function hookActionAfterUpdateCategoryFormHandler(array $params)
    {
        $this->updateIsCategorySpecialStatus($params);
    }

    /**
     * Hook allows to modify Categories form and add additional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     *
     * @throws CategoryException
     */
    public function hookActionAfterCreateCategoryFormHandler(array $params)
    {
        $this->updateIsCategorySpecialStatus($params);
    }
    private function updateIsCategorySpecialStatus(array $params)
    {
        $categoryId = $params['id'];
        /** @var array $categoryFormData */
        $categoryFormData = $params['form_data'];
        $isSpecialCategory = (bool) $categoryFormData['is_special_category'];

        $specialCategoryId = $this->get('mwrspecialcategory.repository.specialcategory')->findIdByCategory($categoryId);

        $specialCategory = new SpecialCategory($specialCategoryId);
        if (0 >= $specialCategory->id) {
            $specialCategory = $this->createIsCategorySpecialStatus($categoryId);
        }
        $specialCategory->is_special_category = $isSpecialCategory;

        try {
            if (false === $specialCategory->update()) {
                throw new CannotToggleIsSpecialCategoryStatusException(
                    sprintf('Failed to change status for specialCategory with id "%s"', $specialCategory->id)
                );
            }
        } catch (PrestaShopException $exception) {
            throw new CannotCreateSpecialCategoryException(
                'An unexpected error occurred when updating specialCategory status'
            );
        }
    }
    protected function createIsCategorySpecialStatus(int $categoryId)
    {
        try {
            $specialCategory = new SpecialCategory();
            $specialCategory->id_category = $categoryId;
            $specialCategory->is_special_category = 0;

            if (false === $specialCategory->save()) {
                throw new CannotCreateSpecialCategoryException(
                    sprintf(
                        'An error occurred when creating specialCategory with category id "%s"',
                        $categoryId
                    )
                );
            }
        } catch (PrestaShopException $exception) {
            throw new CannotCreateSpecialCategoryException(
                sprintf(
                    'An unexpected error occurred when creating specialCategory with category id "%s"',
                    $categoryId
                ),
                0,
                $exception
            );
        }

        return $specialCategory;
    }
    //returns values in smarty and JS: prestashop.modules.mwrspecialcategory.is_special
    public function hookActionFrontControllerSetVariables($params)
    {
        $categoryId = (int)Tools::getValue('id_category');
        if (!$categoryId) {
            return false;
        }
        $is_specialCategory = $this->isSpecialCategory($categoryId);
        if (!$is_specialCategory) {
            return false;
        }
        $mwr_special_category = [
            'is_special' => $is_specialCategory
        ];

        $customerId = $this->context->customer->id;
        $id_lang = $this->context->language->id;

        $redirect_urlId =  Configuration::get($this->getConfigName('CMS_PAGE'));
        $redirect_url = ($this->context->link->getCMSLink($redirect_urlId, null, $id_lang));

        if (!$redirect_url || ($redirect_url == $params['templateVars']['urls']['current_url'])) {
            return false;
        }

        if (!Validate::isLoadedObject($customer = new Customer($customerId))) {
            Tools::redirect($redirect_url);
            return false;
        }
        if (!$customer_domain = $this->getDomainFromEmail($customer->email)) {
            Tools::redirect($redirect_url);
            return false;
        }
        $allowed_domains = explode(',', Configuration::get($this->getConfigName('ALLOWED_DOMAINS') . '_' . $id_lang));

        if (!in_array($customer_domain, $allowed_domains)) {
            return false;
        }


        return $mwr_special_category;
    }
    private function isSpecialCategory($categoryId)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mwrspecialcategory', 'mwr_sc');
        $sql->where('`id_category` = "' . $categoryId . '" and `is_special_category` = "1"');
        $result = Db::getInstance()->getValue($sql);
        if ($result) {
            return true;
        }
        return false;
    }
    private function getDomainFromEmail($email_address)
    {
        if (($email_address = filter_var($email_address, FILTER_VALIDATE_EMAIL)) !== false) {
            $parts = explode('@', $email_address);
            return array_pop($parts);
        }
        return false;
    }
}
