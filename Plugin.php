<?php

namespace Logingrupa\ColorClassifier;

use System\Classes\PluginBase;
use Backend\Classes\NavigationManager;

/**
 * ColorClassifier Plugin — Gel polish color extraction and taxonomy classification.
 *
 * Registers backend navigation, permissions, and plugin metadata for
 * the Logingrupa.ColorClassifier OctoberCMS plugin.
 *
 * @package Logingrupa\ColorClassifier
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array<string, string>
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Color Classifier',
            'description' => 'Gel polish color extraction and taxonomy classification',
            'author'      => 'Logingrupa',
            'icon'        => 'icon-eyedropper',
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     *
     * @return array<string, mixed>
     */
    public function registerNavigation(): array
    {
        return [
            'colorclassifier' => [
                'label'       => 'Color Classifier',
                'url'         => \Backend::url('logingrupa/colorclassifier/colorentries'),
                'icon'        => 'icon-eyedropper',
                'permissions' => ['logingrupa.colorclassifier.manage'],
                'order'       => 500,
            ],
        ];
    }

    /**
     * Registers backend permissions for this plugin.
     *
     * @return array<string, mixed>
     */
    public function registerPermissions(): array
    {
        return [
            'logingrupa.colorclassifier.manage' => [
                'tab'   => 'Color Classifier',
                'label' => 'Manage color classifier entries',
            ],
        ];
    }
}
