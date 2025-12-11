<?php

/**
 * JL Content Fields Filter.
 *
 * @version 	@version@
 * @author		Joomline
 * @copyright  (C) 2017-2023 Arkadiy Sedelnikov, Sergey Tolkachyov, Joomline. All rights reserved.
 * @license 	GNU General Public License version 2 or later; see	LICENSE.txt
 */

namespace Joomla\Component\Jlcontentfieldsfilter\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Controller for items list.
 *
 * @since  1.0.0
 */
class ItemsController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_JLCONTENTFIELDSFILTER_ITEMS';

    /**
     * Method to get the model.
     *
     * @param string $name The model name. Optional.
     * @param string $prefix The class prefix. Optional.
     * @param array $config Configuration array for model. Optional.
     *
     * @return \Joomla\CMS\MVC\Model\BaseDatabaseModel The model instance
     *
     * @since   1.0.0
     */
    public function getModel($name = 'Item', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
