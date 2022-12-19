<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AltoModule extends Module
{
    public function __construct()
    {
        // Задание основных параметров модуля.
        $this->name = 'altomodule';
        $this->tab = 'other';
        $this->version = '1.0.0';
        $this->author = 'Gleb Tuzov';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Alto module');
        $this->description = $this->l('This module takes the price range from the admin panel and displays a message about the number of products in a given range');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('ALTOMODULE_NAME')) {
            $this->warning = $this->l('No name provided');
        }
    }

    public function install()
    {
        return (
            // Регистрация хуков и создание переменныхв в таблице конфигурации.
            parent::install()
            && $this->registerHook('displayHeader')
            && Configuration::updateValue('ALTOMODULE_PRICE_UP', null)
            && Configuration::updateValue('ALTOMODULE_PRICE_DOWN', null)
        );
    }

    public function uninstall()
    {
        return (
            // Удаление переменных из таблицы конфигурации.
            parent::uninstall()
            && Configuration::deleteByName('ALTOMODULE_PRICE_UP')
            && Configuration::deleteByName('ALTOMODULE_PRICE_DOWN')
        );
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {

            $priceUp = (string)Tools::getValue('ALTOMODULE_PRICE_UP');
            $priceDown = (string)Tools::getValue('ALTOMODULE_PRICE_DOWN');


            // Проверка значение.
            if (empty($priceUp) || empty($priceDown) || !Validate::isInt($priceUp) || !Validate::isInt($priceDown)) {
                // Показать ошибку, если цена указана некорректно.
                $output = $this->displayError($this->l('Invalid Price value'));
            } else {
                // Обновить значения и отобразить уведомление об успехе, если значение корректное.
                Configuration::updateValue('ALTOMODULE_PRICE_UP', $priceUp);
                Configuration::updateValue('ALTOMODULE_PRICE_DOWN', $priceDown);
                $output = $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Price range'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Price from'),
                    'name' => 'ALTOMODULE_PRICE_DOWN',
                    'pattern' => "^[ 0-9]+$",
                    'size' => 20,
                    'required' => true,
                )
            )
        );
        $fields_form[1]['form'] = array(
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Price to'),
                    'name' => 'ALTOMODULE_PRICE_UP',
                    'pattern' => "^[ 0-9]+$",
                    'size' => 20,
                    'required' => true,
                )
            ),

            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            )
        );

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        // Загрузка текущих значений в форму.
        $helper->fields_value['ALTOMODULE_PRICE_DOWN'] = Tools::getValue('ALTOMODULE_PRICE_DOWN', Configuration::get('ALTOMODULE_PRICE_DOWN'));
        $helper->fields_value['ALTOMODULE_PRICE_UP'] = Tools::getValue('ALTOMODULE_PRICE_UP', Configuration::get('ALTOMODULE_PRICE_UP'));

        return $helper->generateForm($fields_form);
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayHeader($params)
    {
        // Выполнить подключение к базе даных.
        $db = Db::getInstance(true);

        $price_from = Configuration::get('ALTOMODULE_PRICE_DOWN');
        $price_to = Configuration::get('ALTOMODULE_PRICE_UP');

        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('ps_product', 'p');
        $sql->where('p.price BETWEEN '.$price_from.' AND '.$price_to);
        $db->executeS($sql);
        $count_item = $db->numRows();

        // Передача переменных.
        $this->context->smarty->assign([
            'price_from' => $price_from,
            'price_to' => $price_to,
            'count_item' => $count_item,
        ]);

        return $this->display(__FILE__, 'altomodule.tpl');
    }
}