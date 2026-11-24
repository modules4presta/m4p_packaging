<?php

/**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

class Cart extends CartCore
{
    public function getDeliveryOptionList(?Country $default_country = null, $flush = false)
    {
        $options = parent::getDeliveryOptionList($default_country, $flush);
        $module = Module::getInstanceByName('m4p_packaging');
        if (!$module instanceof M4p_packaging || !$module->active || !is_array($options)) {
            return $options;
        }

        return $module->filterDeliveryOptions($this, $options);
    }
}
