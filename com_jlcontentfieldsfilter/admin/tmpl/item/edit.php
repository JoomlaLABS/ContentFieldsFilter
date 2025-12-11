<?php

/**
 * JL Content Fields Filter.
 *
 * @version 	@version@
 * @author		Joomline
 * @copyright  (C) 2017-2023 Arkadiy Sedelnikov, Sergey Tolkachyov, Joomline. All rights reserved.
 * @license 	GNU General Public License version 2 or later; see	LICENSE.txt
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

// Load module language files for filter field translations
$lang = Factory::getApplication()->getLanguage();
$currentLang = $lang->getTag();

// Force load module language files from site language directory
$langPath = JPATH_SITE . '/language/' . $currentLang . '/mod_jlcontentfieldsfilter.ini';
$langPathFallback = JPATH_SITE . '/language/en-GB/mod_jlcontentfieldsfilter.ini';

// Try to load from site language directory first (where module languages are installed)
if (file_exists($langPath)) {
    $lang->load('mod_jlcontentfieldsfilter', JPATH_SITE, $currentLang, true, false);
}
// Load fallback English
if ($currentLang !== 'en-GB' && file_exists($langPathFallback)) {
    $lang->load('mod_jlcontentfieldsfilter', JPATH_SITE, 'en-GB', true, false);
}

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

// Load module CSS and JS for filter fields styling and functionality
HTMLHelper::_('stylesheet', 'mod_jlcontentfieldsfilter/jlcontentfilter.css', ['version' => 'auto', 'relative' => true]);
HTMLHelper::_('script', 'mod_jlcontentfieldsfilter/jlcontentfilter.js', ['version' => 'auto', 'relative' => true]);

$app = Factory::getApplication();
$input = $app->input;

// Function to load filter fields
function loadFilterFields($catid, $filterString = '') {
    if ($catid <= 0) {
        return '<div class="alert alert-info">' .
               '<span class="icon-info-circle" aria-hidden="true"></span> ' .
               Text::_('COM_JLCONTENTFIELDSFILTER_SELECT_CATEGORY_FIRST') .
               '</div>';
    }
    
    try {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
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
        
        // Load helper
        $helperFile = JPATH_ROOT . '/modules/mod_jlcontentfieldsfilter/src/Helper/JlcontentfieldsfilterHelper.php';
        if (!file_exists($helperFile)) {
            return '<div class="alert alert-danger">Helper file not found: ' . htmlspecialchars($helperFile) . '</div>';
        }
        
        require_once $helperFile;
        
        $helperClass = '\\Joomla\\Module\\Jlcontentfieldsfilter\\Site\\Helper\\JlcontentfieldsfilterHelper';
        $helper = new $helperClass();
        
        // Load field types and params from database to determine how to parse values
        $fieldTypes = [];
        $fieldLayouts = [];
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'type', 'params']))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));
        $fieldsData = $db->setQuery($query)->loadObjectList('id');
        foreach ($fieldsData as $fieldData) {
            $fieldTypes[$fieldData->id] = $fieldData->type;
            $fieldParams = new Registry($fieldData->params);
            $fieldLayouts[$fieldData->id] = $fieldParams->get('content_filter', '');
        }
        
        // Parse current filters from format: 5=1,2,3&6=value&3[from]=50&3[to]=80
        // Clean up any leading/trailing & characters (sometimes saved as &5=1,2,3&)
        $currentFilters = [];
        if (!empty($filterString)) {
            $filterString = trim($filterString, '&'); // Remove leading/trailing &
            $pairs = explode('&', $filterString);
            foreach ($pairs as $pair) {
                $pair = trim($pair);
                if (empty($pair) || strpos($pair, '=') === false) {
                    continue;
                }
                list($fieldId, $value) = explode('=', $pair, 2);
                $fieldId = urldecode(trim($fieldId));
                $value = urldecode(trim($value));
                
                // Check if it's a range field: fieldid[from] or fieldid[to]
                if (preg_match('/^(\d+)\[(from|to)\]$/', $fieldId, $matches)) {
                    $realFieldId = $matches[1];
                    $rangeType = $matches[2]; // 'from' or 'to'
                    
                    if (!isset($currentFilters[$realFieldId])) {
                        $currentFilters[$realFieldId] = [];
                    }
                    $currentFilters[$realFieldId][$rangeType] = $value;
                } else {
                    // Regular field: behavior depends on field type AND layout
                    $fieldType = isset($fieldTypes[$fieldId]) ? $fieldTypes[$fieldId] : 'text';
                    $fieldLayout = isset($fieldLayouts[$fieldId]) ? $fieldLayouts[$fieldId] : '';
                    
                    // Layouts that require array format (convert non-array to empty array)
                    // - radio: layout always expects array
                    // - checkboxes: layout always expects array
                    // Even if field type is 'list', if content_filter=radio, it uses radio layout
                    $arrayLayouts = ['radio', 'checkboxes'];
                    
                    if (in_array($fieldLayout, $arrayLayouts) || $fieldType === 'checkboxes') {
                        // These fields/layouts always expect array, even for single value
                        if (strpos($value, ',') !== false) {
                            // Multiple values: '1,2,3' becomes ['1','2','3']
                            $currentFilters[$fieldId] = explode(',', $value);
                        } else {
                            // Single value: '1' becomes ['1'] (array with one element)
                            $currentFilters[$fieldId] = [$value];
                        }
                    } else {
                        // Text, list (with list layout) and other fields expect string
                        $currentFilters[$fieldId] = $value;
                    }
                }
            }
        }
        
        // Get fields
        $fields = $helper->getFields($params, $catid, $currentFilters, $moduleData->id, 'com_content');
        
        if (empty($fields)) {
            return '<div class="alert alert-info">' .
                   '<span class="icon-info-circle" aria-hidden="true"></span> ' .
                   Text::_('COM_JLCONTENTFIELDSFILTER_NO_FIELDS_FOUND') .
                   '</div>';
        }
        
        $html = '<div class="row g-3">';
        foreach ($fields as $field) {
            $html .= '<div class="col-md-6 jlmf-section">' . $field . '</div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (\Exception $e) {
        return '<div class="alert alert-danger">' .
               '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) .
               '</div>';
    }
}
?>

<form action="<?php echo Route::_('index.php?option=com_jlcontentfieldsfilter&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="item-form" class="form-validate">
    
    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <?php echo $this->form->renderFieldset('details'); ?>
                </div>
            </div>
            
            <?php if ($this->item->catid > 0) : ?>
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><?php echo Text::_('COM_JLCONTENTFIELDSFILTER_FIELDSET_FILTERS'); ?></h3>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllFilters()">
                            <span class="icon-delete" aria-hidden="true"></span>
                            <?php echo Text::_('COM_JLCONTENTFIELDSFILTER_CLEAR_FILTERS'); ?>
                        </button>
                    </div>
                    <div class="card-body" id="jlcontentfieldsfilter_filter_params">
                        <?php echo loadFilterFields($this->item->catid, $this->item->filter); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <?php echo $this->form->renderField('state'); ?>
                    <?php echo $this->form->renderField('id'); ?>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
(function() {
    'use strict';
    
    // Clear all filter fields in backend - exposed to global scope
    window.clearAllFilters = function() {
        const filterContainer = document.getElementById('jlcontentfieldsfilter_filter_params');
        if (!filterContainer) return;
        
        // Clear all checkboxes and radios
        filterContainer.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked').forEach(input => {
            input.checked = false;
        });
        
        // Clear all text inputs that are NOT part of range sliders
        filterContainer.querySelectorAll('input[type="text"]').forEach(input => {
            if (!input.closest('.range-sliders')) {
                input.value = '';
            }
        });
        
        // Reset all select elements to first option
        filterContainer.querySelectorAll('select').forEach(select => {
            select.selectedIndex = 0;
        });
        
        // Reset noUiSlider ranges to min/max values
        filterContainer.querySelectorAll('.jlmf-range').forEach(function(sliderEl) {
            const slider = sliderEl.noUiSlider;
            if (slider) {
                const container = sliderEl.closest('.range-sliders');
                if (container) {
                    const inputMin = container.querySelector('.input-min');
                    const inputMax = container.querySelector('.input-max');
                    const min = parseInt(sliderEl.getAttribute('data-min'));
                    const max = parseInt(sliderEl.getAttribute('data-max'));
                    if (!isNaN(min) && !isNaN(max)) {
                        if (inputMin) inputMin.value = min;
                        if (inputMax) inputMax.value = max;
                        slider.set([min, max]);
                    }
                }
            }
        });
        
        // Trigger change event to update filter string
        const filterCard = document.querySelector('.card-body .row.g-3');
        if (filterCard) {
            filterCard.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    
    // Function to serialize filter parameters into filter string
    // Format: fieldid=val1,val2,val3&fieldid2=value (matching createFilterString in helper)
    function serializeFilterParams() {
        const filterCard = document.querySelector('.card-body .row.g-3');
        if (!filterCard) return '';
        
        const params = {};
        
        // Get all input elements in filter parameters section
        const inputs = filterCard.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.name || !input.name.startsWith('jlcontentfieldsfilter[')) return;
            
            // Extract field ID from jlcontentfieldsfilter[fieldid] or jlcontentfieldsfilter[fieldid][]
            const match = input.name.match(/jlcontentfieldsfilter\[(\d+)\]/);
            if (!match) return;
            
            const fieldId = match[1];
            
            // Handle different input types
            if (input.type === 'checkbox') {
                if (input.checked && input.value) {
                    if (!params[fieldId]) {
                        params[fieldId] = [];
                    }
                    params[fieldId].push(input.value);
                }
            } else if (input.type === 'radio') {
                if (input.checked && input.value) {
                    params[fieldId] = input.value;
                }
            } else if (input.value) {
                // For text fields, check if it's a range (from/to)
                if (input.name.includes('[from]') || input.name.includes('[to]')) {
                    // Range fields are handled separately below
                    return;
                } else {
                    params[fieldId] = input.value;
                }
            }
        });
        
        // Second pass: handle range fields (from/to)
        const rangeInputs = filterCard.querySelectorAll('input[name*="[from]"], input[name*="[to]"]');
        rangeInputs.forEach(input => {
            if (!input.name || !input.value) return;
            
            // Extract field ID and type: jlcontentfieldsfilter[3][from] or jlcontentfieldsfilter[3][to]
            const match = input.name.match(/jlcontentfieldsfilter\[(\d+)\]\[(from|to)\]/);
            if (!match) return;
            
            const fieldId = match[1];
            const rangeType = match[2]; // 'from' or 'to'
            
            if (!params[fieldId]) {
                params[fieldId] = {};
            }
            params[fieldId][rangeType] = input.value;
        });
        
        // Build query string in format: fieldid=val1,val2&fieldid2=value&fieldid3[from]=50&fieldid3[to]=80
        // NO leading/trailing & - only between field pairs
        const parts = [];
        for (const fieldId in params) {
            const value = params[fieldId];
            if (Array.isArray(value)) {
                // Multiple values: join with comma (no encoding on commas)
                if (value.length > 0) {
                    // Encode individual values but not the comma separator
                    const encodedValues = value.map(v => encodeURIComponent(v));
                    parts.push(fieldId + '=' + encodedValues.join(','));
                }
            } else if (typeof value === 'object' && (value.from || value.to)) {
                // Range field: output as fieldid[from]=val&fieldid[to]=val
                if (value.from) {
                    parts.push(fieldId + '[from]=' + encodeURIComponent(value.from));
                }
                if (value.to) {
                    parts.push(fieldId + '[to]=' + encodeURIComponent(value.to));
                }
            } else if (value !== '' && value !== null && value !== undefined) {
                // Single value - only add if not empty
                parts.push(fieldId + '=' + encodeURIComponent(value));
            }
        }
        
        return parts.join('&');
    }
    
    // Update filter field before form submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('item-form');
        if (!form) return;
        
        // Initialize JlContentFieldsFilter.params for backend compatibility
        // This allows clearRadio() to work without full module initialization
        if (typeof JlContentFieldsFilter !== 'undefined') {
            JlContentFieldsFilter.params = JlContentFieldsFilter.params || [];
            JlContentFieldsFilter.params['item-form'] = {
                autho_send: 0,
                ajax: 0
            };
        }
        
        form.addEventListener('submit', function(e) {
            const filterField = document.getElementById('jform_filter');
            if (filterField) {
                const filterString = serializeFilterParams();
                filterField.value = filterString;
            }
        });
        
        // Also update on any filter parameter change (for real-time updates)
        const filterCard = document.querySelector('.card-body .row.g-3');
        if (filterCard) {
            filterCard.addEventListener('change', function() {
                const filterField = document.getElementById('jform_filter');
                if (filterField) {
                    const filterString = serializeFilterParams();
                    filterField.value = filterString;
                }
            });
        }
    });
})();
</script>
