<?php

declare(strict_types=1);

namespace Guzaba2;

use Azonmedia\Components\BaseComponent;
use Azonmedia\Components\Interfaces\ComponentInterface;

/**
 * Class Component
 *
 * @package Guzaba2
 */
class Component extends BaseComponent
{

    protected const COMPONENT_NAME = "Guzaba2 Framework";
    //https://components.platform.guzaba.org/component/{vendor}/{component}
    protected const COMPONENT_URL = 'https://framework.guzaba.org/';
    //protected const DEV_COMPONENT_URL//this should come from composer.json
    protected const COMPONENT_NAMESPACE = 'GuzabaPlatform\\Crud';
    protected const COMPONENT_VERSION = '0.0.6';//TODO update this to come from the Composer.json file of the component
    protected const VENDOR_NAME = 'Azonmedia';
    protected const VENDOR_URL = 'https://azonmedia.com';
    protected const ERROR_REFERENCE_URL = 'https://github.com/AzonMedia/guzaba2-docs/tree/master/ErrorReference/';
}
