<?php

namespace Logingrupa\ColorClassifier\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * ColorFamilies Backend Controller - the Families editor.
 *
 * Lists, creates, edits and reorders color families. There is no delete
 * action: a family is retired with its Active switch.
 *
 * @package Logingrupa\ColorClassifier\Controllers
 */
class ColorFamilies extends Controller
{
    /**
     * Implemented controller behaviors.
     *
     * @var array<int, string>
     */
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    /** @var string Path to the list behavior configuration file. */
    public $listConfig = 'config_list.yaml';

    /** @var string Path to the form behavior configuration file. */
    public $formConfig = 'config_form.yaml';

    /** @var string Path to the reorder behavior configuration file. */
    public $reorderConfig = 'config_reorder.yaml';

    /**
     * Required permission codes to access this controller.
     *
     * @var array<int, string>
     */
    public $requiredPermissions = ['logingrupa.colorclassifier.manage'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Logingrupa.ColorClassifier', 'colorclassifier', 'families');
    }
}
