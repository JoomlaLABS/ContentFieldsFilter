<?php

/**
 * JL Content Fields Filter.
 *
 * @version 	@version@
 * @author		Joomline
 * @copyright  (C) 2017-2023 Arkadiy Sedelnikov, Sergey Tolkachyov, Joomline. All rights reserved.
 * @license 	GNU General Public License version 2 or later; see	LICENSE.txt
 */

namespace Joomla\Component\Jlcontentfieldsfilter\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * Dynamic filter fields loader.
 *
 * @since  1.0.0
 */
class FilterfieldsField extends FormField
{
    /**
     * The form field type.
     *
     * @var string
     * @since  1.0.0
     */
    protected $type = 'Filterfields';

    /**
     * Method to get the field input markup.
     *
     * @return string The field input markup.
     *
     * @since   1.0.0
     */
    protected function getInput()
    {
        $doc = Factory::getDocument();

        // Get category ID from form data
        $catid = (int) $this->form->getValue('catid', null, 0);

        // Get filter string from form data
        $filterString = $this->form->getValue('filter', null, '');

        $html = [];

        $html[] = '<div id="filter-fields-container" class="mt-3 p-3 border rounded bg-light">';

        if ($catid > 0) {
            // Load fields for the selected category
            try {
                $fieldsHtml = $this->loadFilterFields($catid, $filterString);
                $html[]     = $fieldsHtml;
            } catch (\Exception $e) {
                $html[] = '<div class="alert alert-danger">';
                $html[] = '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
                $html[] = '</div>';
            }
        } else {
            $html[] = '<div class="alert alert-info">';
            $html[] = '<span class="icon-info-circle" aria-hidden="true"></span> ';
            $html[] = Text::_('COM_JLCONTENTFIELDSFILTER_SELECT_CATEGORY_FIRST');
            $html[] = '</div>';
        }

        $html[] = '</div>';

        // Add JavaScript to reload fields when category changes
        $doc->addScriptDeclaration($this->getJavaScript());

        return implode("\n", $html);
    }

    /**
     * Load filter fields for a specific category.
     *
     * @param int $catid Category ID
     * @param string $filterString Filter string to parse
     *
     * @return string HTML markup
     *
     * @since   1.0.0
     */
    protected function loadFilterFields($catid, $filterString = '')
    {
        // Parse filter string to get current values
        $currentFilters = [];
        if (!empty($filterString)) {
            parse_str($filterString, $currentFilters);
            $currentFilters = $currentFilters['jlcontentfieldsfilter'] ?? [];
        }

        try {
            // Get a published module instance
            $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_jlcontentfieldsfilter'))
                ->where($db->quoteName('published') . ' = 1')
                ->setLimit(1);

            $moduleData = $db->setQuery($query)->loadObject();

            if (!$moduleData) {
                return '<div class="alert alert-warning">' .
                       '<span class="icon-warning" aria-hidden="true"></span> ' .
                       Text::_('COM_JLCONTENTFIELDSFILTER_MODULE_NOT_FOUND') .
                       '</div>';
            }

            $params = new Registry($moduleData->params);

            // Load the helper class directly
            $helperFile = JPATH_ROOT . '/modules/mod_jlcontentfieldsfilter/src/Helper/JlcontentfieldsfilterHelper.php';

            if (!file_exists($helperFile)) {
                throw new \Exception('Helper file not found at: ' . $helperFile);
            }

            require_once $helperFile;

            $helperClass = '\\Joomla\\Module\\Jlcontentfieldsfilter\\Site\\Helper\\JlcontentfieldsfilterHelper';
            if (!class_exists($helperClass)) {
                throw new \Exception('Helper class not found: ' . $helperClass);
            }

            $helper = new $helperClass();

            // Get fields for this category
            $fields = $helper->getFields($params, $catid, $currentFilters, $moduleData->id, 'com_content');

            if (empty($fields)) {
                return '<div class="alert alert-info">' .
                       '<span class="icon-info-circle" aria-hidden="true"></span> ' .
                       Text::_('COM_JLCONTENTFIELDSFILTER_NO_FIELDS_FOUND') .
                       '</div>';
            }

            $html   = [];
            $html[] = '<div class="row g-3">';

            foreach ($fields as $field) {
                $html[] = '<div class="col-md-6">';
                $html[] = $field;
                $html[] = '</div>';
            }

            $html[] = '</div>';

            return implode("\n", $html);

        } catch (\Exception $e) {
            return '<div class="alert alert-danger">' .
                   '<span class="icon-error" aria-hidden="true"></span> ' .
                   Text::sprintf('COM_JLCONTENTFIELDSFILTER_ERROR_LOADING_FIELDS', $e->getMessage()) .
                   '</div>';
        }
    }

    /**
     * Get JavaScript for dynamic field loading.
     *
     * @return string JavaScript code
     *
     * @since   1.0.0
     */
    protected function getJavaScript()
    {
        return <<<JS
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const catidField = document.getElementById('jform_catid');
        const container = document.getElementById('filter-fields-container');
        
        if (!catidField || !container) {
            return;
        }
        
        catidField.addEventListener('change', function() {
            const catid = this.value;
            
            if (!catid || catid == '0') {
                container.innerHTML = '<div class="alert alert-info">' +
                    '<span class="icon-info-circle" aria-hidden="true"></span> ' +
                    'Please select a category first.' +
                    '</div>';
                return;
            }
            
            // Show loading message
            container.innerHTML = '<div class="spinner-border" role="status">' +
                '<span class="visually-hidden">Loading...</span>' +
                '</div>';
            
            // Reload the form to get fields for new category
            const form = document.getElementById('item-form');
            if (form) {
                // Create a hidden input to trigger field reload
                const reloadInput = document.createElement('input');
                reloadInput.type = 'hidden';
                reloadInput.name = 'reload_fields';
                reloadInput.value = '1';
                form.appendChild(reloadInput);
                
                // Submit form to reload
                const taskInput = form.querySelector('input[name="task"]');
                if (taskInput) {
                    taskInput.value = 'item.reload';
                }
                
                form.submit();
            }
        });
    });
})();
JS;
    }
}
