<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Hackathon
 * @package     Hackathon_MageMonitoring
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Hackathon_MageMonitoring_Block_Tab_Config extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('monitoring/tab/config.phtml');
    }

/**
     * Prepare Global Layout
     *
     * @return Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Main
     */
    protected function _prepareLayout()
    {
        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        $this->setChild('tab_tree',
            $this->getLayout()->createBlock('magemonitoring/tab_config_tree_tab')
        );

        $this->setChild('edit_widget_config_form',
            $this->getLayout()->createBlock('magemonitoring/tab_config_form_widgetConf')
        );

        $this->setChild('delete_node_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                'label'     => Mage::helper('magemonitoring')->__('Delete Selected Node/Leaf'),
                'onclick'   => 'editSet.deleteSelected();',
                'class'     => 'delete'
        )));

        $this->setChild('add_tab_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                'label'     => Mage::helper('catalog')->__('Add New Tab'),
                'onclick'   => 'editSet.addTab();',
                'class'     => 'add'
        )));

        $this->setChild('back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                'label'     => Mage::helper('catalog')->__('Back'),
                'onclick'   => 'setLocation(\''.$this->getUrl('*/*/').'\')',
                'class'     => 'back'
        )));

        $this->setChild('save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                'label'     => Mage::helper('catalog')->__('Save Tab Config'),
                'onclick'   => 'editSet.save();',
                'class'     => 'save'
        )));

        $this->setChild('reset_config_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                'label'     => Mage::helper('catalog')->__('Factory Configuration'),
                'onclick'   => 'deleteConfirm(\''. $this->jsQuoteEscape(Mage::helper('catalog')->__('Really wipe module config from database?'))
                                              . '\', \'' . $this->getUrl('*/*/resetConfig') . '\')',
                'class'     => 'delete'
        )));

        return parent::_prepareLayout();
    }

    /**
     * Retrieve Attribute Set Group Tree HTML
     *
     * @return string
     */
    public function getTabTreeHtml()
    {
        return $this->getChildHtml('tab_tree');
    }

    /**
     * Retrieve Attribute Set Edit Form HTML
     *
     * @return string
     */
    public function getEditWidgetConfigFormHtml()
    {
        return $this->getChildHtml('edit_widget_config_form');
    }

    /**
     * Retrieve Block Header Text
     *
     * @return string
     */
    protected function _getHeader()
    {
        return Mage::helper('catalog')->__('Edit Tab Config');
    }

    /**
     * Retrieve Attribute Set Save URL
     *
     * @return string
     */
    public function getSaveUrl()
    {
        return $this->getUrl('*/mageMonitoring_widgetAjax/saveTabConfig');
    }

    public function getEditWidgetConfigFormUrl()
    {
        return $this->getUrl('*/mageMonitoring_widgetAjax/getWidgetConfigForm');
    }

    /**
     * Retrieve Attribute Set Group Save URL
     *
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl('*/catalog_product_group/save', array('id' => $this->_getSetId()));
    }

    /**
     * Retrieve Tab Tree as JSON format
     *
     * @return string
     */
    public function getTabTreeJson()
    {
        $tabsJson = array();

        if (!$this->_configuredWidgets) {
            $this->_configuredWidgets = Mage::helper('magemonitoring')->getConfiguredWidgets();
        }

        $tabs = Mage::helper('magemonitoring')->getConfiguredTabs();

        /* @var $node MageMonitoring Tab */
        foreach ($tabs as $tabId => $tab) {
            $tabJson = array();
            $tabJson['text']       = $tab['title'];
            $tabJson['id']         = $tabId;
            $tabJson['cls']        = 'folder';
            $tabJson['allowDrop']  = true;
            $tabJson['allowDrag']  = true;


            if (is_array($tab['widgets'])) {
                $tabJson['children'] = array();
                foreach ($this->_configuredWidgets[$tabId] as $widgetDbId => $widget) {
                    /* @var $child MageMonitoring Widget */
                    $widgetJson = array(
                        'text'              => $widget->getName(),
                        'id'                => $widget->getConfigId(),
                        'impl'              => $widget->getId(),
                        'cls'               => (false) ? 'system-leaf' : 'leaf',
                        'allowDrop'         => false,
                        'allowDrag'         => true,
                        'leaf'              => true,
                        'is_user_defined'   => false,
                        'is_configurable'   => false,
                        'entity_id'         => $widget->getConfigId()
                    );

                    $tabJson['children'][] = $widgetJson;
                }
            } else {
                $tabJson['allowChildren'] = false;
            }

            $tabsJson[] = $tabJson;
        }
        return Mage::helper('core')->jsonEncode($tabsJson);
    }

    /**
     * Retrieve Unused in Attribute Set Attribute Tree as JSON
     *
     * @return string
     */
    public function getWidgetTreeJson()
    {
        $widgetsJson = array();

        if (!$this->_activeWidgets) {
            $w = Mage::helper('magemonitoring')->getActiveWidgets('*', null, false);
            uasort($w, array($this, 'sortWidgetsByName'));
            $this->_activeWidgets = $w;
        }

        foreach ($this->_activeWidgets as $id => $widget) {
            $widgetJson = array(
                'text'              => $widget->getName(),
                'id'                => $widget->getConfigId(),
                'impl'              => $widget->getId(),
                'cls'               => 'leaf',
                'allowDrop'         => false,
                'allowDrag'         => true,
                'leaf'              => true,
                'is_user_defined'   => false,
                'is_configurable'   => false,
                'entity_id'         => $widget->getConfigId()
            );

            $widgetsJson[] = $widgetJson;
        }

        if (count($widgetsJson) == 0) {
            $widgetsJson[] = array(
                'text'      => Mage::helper('magemonitoring')->__('Empty'),
                'id'        => 'empty',
                'cls'       => 'folder',
                'allowDrop' => false,
                'allowDrag' => false,
            );
        }

        return Mage::helper('core')->jsonEncode($widgetsJson);
    }

    /**
     * Compare function for uasort(). Sorts by widget name.
     *
     * @param array $a
     * @param array $b
     * @return number
     */
    protected function sortWidgetsByName($a, $b)
    {
        return strnatcmp($a->getName(), $b->getName());
    }

    /**
     * Retrieve Back Button HTML
     *
     * @return string
     */
    public function getBackButtonHtml()
    {
        return $this->getChildHtml('back_button');
    }

    /**
     * Retrieve Reset Button HTML
     *
     * @return string
     */
    public function getResetButtonHtml()
    {
        return $this->getChildHtml('reset_config_button');
    }

    /**
     * Retrieve Save Button HTML
     *
     * @return string
     */
    public function getSaveButtonHtml()
    {
        return $this->getChildHtml('save_button');
    }

    /**
     * Retrieve Delete Group Button HTML
     *
     * @return string
     */
    public function getDeleteNodeButton()
    {
        return $this->getChildHtml('delete_node_button');
    }

    /**
     * Retrieve Add New Group Button HTML
     *
     * @return string
     */
    public function getAddTabButton()
    {
        return $this->getChildHtml('add_tab_button');
    }

}
