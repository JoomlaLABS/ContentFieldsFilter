<?php

/**
 * JL Content Fields Filter.
 *
 * @version 	@version@
 * @author		Joomline
 * @copyright  (C) 2017-2023 Arkadiy Sedelnikov, Sergey Tolkachyov, Joomline. All rights reserved.
 * @license 	GNU General Public License version 2 or later; see	LICENSE.txt
 */

namespace Joomla\Component\Jlcontentfieldsfilter\Administrator\View\Items;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Jlcontentfieldsfilter\Administrator\Helper\JlcontentfieldsfilterHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View to display a list of items.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * An array of items
     *
     * @var array
     * @since  1.0.0
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var \Joomla\CMS\Pagination\Pagination
     * @since  1.0.0
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var \Joomla\CMS\Form\Form
     * @since  1.0.0
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var array
     * @since  1.0.0
     */
    public $activeFilters;

    /**
     * Category options for select
     *
     * @var string
     * @since  1.0.0
     */
    public $categoryOptions;

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
        $this->items          = $this->get('Items');
        $this->pagination     = $this->get('Pagination');
        $this->state          = $this->get('State');
        $this->filterForm     = $this->get('FilterForm');
        $this->activeFilters  = $this->get('ActiveFilters');
        $this->categoryOptions = $this->get('CategoryOptions');

        // Check for errors
        if (\count($errors = $this->get('Errors'))) {
            throw new \Exception(\implode("\n", $errors), 500);
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
        $canDo = JlcontentfieldsfilterHelper::getActions();
        $user  = Factory::getApplication()->getIdentity();

        // Get the toolbar object instance
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_JLCONTENTFIELDSFILTER_MANAGER_ITEMS'), 'generic');

        if ($canDo->{'core.create'}) {
            $toolbar->addNew('item.add');
        }

        if ($canDo->{'core.edit.state'}) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            $childBar->publish('items.publish')->listCheck(true);
            $childBar->unpublish('items.unpublish')->listCheck(true);
        }

        if ($this->state->get('filter.publish') == -2 && $canDo->{'core.delete'}) {
            $toolbar->delete('items.delete')
                ->text('JTOOLBAR_EMPTY_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        } elseif ($canDo->{'core.edit.state'}) {
            $childBar->trash('items.trash')->listCheck(true);
        }

        if ($user->authorise('core.admin', 'com_jlcontentfieldsfilter') || $user->authorise('core.options', 'com_jlcontentfieldsfilter')) {
            $toolbar->preferences('com_jlcontentfieldsfilter');
        }

        ToolbarHelper::help('', false, 'https://joomline.net/extensions/seo-for-jl-content-fields-filter.html');
    }
}
