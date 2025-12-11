<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_jlcontentfieldsfilter
 *
 * @version     @version@
 * @author      Joomline
 * @copyright   (C) 2017-2023 Arkadiy Sedelnikov, Sergey Tolkachyov, Joomline. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jlcontentfieldsfilter\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Items model for jlcontentfieldsfilter component.
 *
 * @since  1.0.0
 */
class ItemsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array $config Configuration array
     *
     * @since   1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'catid', 'a.catid',
                'meta_title', 'a.meta_title',
                'publish', 'a.publish',
                'filter_hash', 'a.filter_hash',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to get the filter form.
     *
     * @param array  $data     Data for the form.
     * @param bool   $loadData True to load the default data.
     *
     * @return Form|null The Form object or null on error.
     *
     * @since   1.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        // Add the forms directory to the form search paths
        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_jlcontentfieldsfilter/forms');
        
        // Load the filter form from XML
        $form = $this->loadForm(
            'com_jlcontentfieldsfilter.filter',
            'filter_items',
            [
                'control' => '',
                'load_data' => $loadData
            ]
        );

        if (!$form) {
            return null;
        }

        return $form;
    }

    /**
     * Build the query to get the list of records.
     *
     * @return \Joomla\Database\QueryInterface The database query object.
     *
     * @since   1.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select fields
        $query->select($db->quoteName([
            'a.id',
            'a.catid',
            'a.filter_hash',
            'a.filter',
            'a.meta_title',
            'a.meta_desc',
            'a.meta_keywords',
            'a.publish',
        ]))
            ->from($db->quoteName('#__jlcontentfieldsfilter_data', 'a'));

        // Join with categories to get category name
        $query->select($db->quoteName('c.title', 'category_title'))
            ->leftJoin(
                $db->quoteName('#__categories', 'c') .
                ' ON ' . $db->quoteName('c.id') .
                ' = ' . $db->quoteName('a.catid')
            );

        // Filter by category
        $categoryId = $this->getState('filter.catid');
        if (is_numeric($categoryId) && $categoryId > 0) {
            $query->where($db->quoteName('a.catid') . ' = :catid')
                ->bind(':catid', $categoryId, ParameterType::INTEGER);
        }

        // Filter by published state
        $published = $this->getState('filter.publish');
        if (is_numeric($published)) {
            $query->where($db->quoteName('a.publish') . ' = :publish')
                ->bind(':publish', $published, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true)) . '%';
            $query->where('(' .
                $db->quoteName('a.meta_title') . ' LIKE :search1 OR ' .
                $db->quoteName('a.meta_desc') . ' LIKE :search2 OR ' .
                $db->quoteName('a.filter') . ' LIKE :search3' .
                ')')
                ->bind(':search1', $search)
                ->bind(':search2', $search)
                ->bind(':search3', $search);
        }

        // Add ordering
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param string $ordering  An optional ordering field.
     * @param string $direction An optional direction (asc|desc).
     *
     * @return void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = 'a.id', $direction = 'DESC')
    {
        // Load the filter state
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $categoryId = $this->getUserStateFromRequest($this->context . '.filter.catid', 'filter_catid', '');
        $this->setState('filter.catid', $categoryId);

        $published = $this->getUserStateFromRequest($this->context . '.filter.publish', 'filter_publish', '');
        $this->setState('filter.publish', $published);

        parent::populateState($ordering, $direction);
    }

    /**
     * Get category options for select field.
     *
     * @return string HTML options for category select
     *
     * @since   1.0.0
     */
    public function getCategoryOptions()
    {
        $categoryOptions = HTMLHelper::_(
            'select.options',
            HTMLHelper::_('category.options', 'com_content'),
            'value',
            'text',
            ['class' => 'form-select']
        );
        return $categoryOptions;
    }

    /**
     * Get items from a specific category with filters.
     *
     * @param int   $categoryId Category ID
     * @param array $filterData Filter data array
     *
     * @return array Array of filter items
     *
     * @since   1.0.0
     */
    public function getItemsByFilters($categoryId, $filterData)
    {
        if (!is_numeric($categoryId) || $categoryId < 1) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__jlcontentfieldsfilter_data'))
            ->where($db->quoteName('catid') . ' = :catid')
            ->bind(':catid', $categoryId, ParameterType::INTEGER);

        // If we have filter data, match by filter string
        if (!empty($filterData) && \is_array($filterData)) {
            $helper        = new \Joomla\Component\Jlcontentfieldsfilter\Administrator\Helper\JlcontentfieldsfilterHelper();
            $filter        = $helper::createFilterString($filterData);
            $unsafe_filter = $helper::createFilterString($filterData, false);
            $hash          = $helper::createHash($filter);
            $unsafe_hash   = $helper::createHash($unsafe_filter);

            $query->where('(' .
                $db->quoteName('filter_hash') . ' = :hash OR ' .
                $db->quoteName('filter_hash') . ' = :unsafe_hash' .
                ')')
                ->bind(':hash', $hash)
                ->bind(':unsafe_hash', $unsafe_hash);
        }

        $query->order($db->quoteName('id') . ' DESC');

        return $db->setQuery($query)->loadObjectList('id') ?: [];
    }
}
