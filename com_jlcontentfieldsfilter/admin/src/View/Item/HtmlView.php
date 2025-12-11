<?php

/**
 * JL Content Fields Filter.
 *
 * @version 	@version@
 * @author		Joomline
 * @copyright  (C) 2017-2023 Arkadiy Sedelnikov, Sergey Tolkachyov, Joomline. All rights reserved.
 * @license 	GNU General Public License version 2 or later; see	LICENSE.txt
 */

namespace Joomla\Component\Jlcontentfieldsfilter\Administrator\View\Item;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View to edit an item.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form object.
     *
     * @var \Joomla\CMS\Form\Form
     * @since  1.0.0
     */
    protected $form;

    /**
     * The item object.
     *
     * @var object
     * @since  1.0.0
     */
    protected $item;

    /**
     * The model state.
     *
     * @var \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param string $tpl The name of the template file to parse
     *
     * @return void
     *
     * @since   1.0.0
     */
    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        // Check for errors
        if (\count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $isNew = ($this->item->id == 0);
        $title = $isNew ? Text::_('COM_JLCONTENTFIELDSFILTER_MANAGER_ITEM_NEW') : Text::_('COM_JLCONTENTFIELDSFILTER_MANAGER_ITEM_EDIT');

        ToolbarHelper::title($title, 'generic');

        ToolbarHelper::apply('item.apply');
        ToolbarHelper::save('item.save');

        if ($isNew) {
            ToolbarHelper::cancel('item.cancel');
        } else {
            ToolbarHelper::cancel('item.cancel', 'JTOOLBAR_CLOSE');
        }

        ToolbarHelper::help('', false, 'https://joomline.net/extensions/seo-for-jl-content-fields-filter.html');
    }
}
