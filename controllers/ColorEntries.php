<?php

namespace Logingrupa\ColorClassifier\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Logingrupa\ColorClassifier\Classes\BatchProcessor;

/**
 * ColorEntries Backend Controller — manages the color classification list view.
 *
 * Provides a backend list of processed color entries with Process All,
 * Process New, and Export actions. Uses OctoberCMS ListController and
 * ImportExportController behaviors.
 *
 * @package Logingrupa\ColorClassifier\Controllers
 */
class ColorEntries extends Controller
{
    /**
     * Implemented controller behaviors.
     *
     * @var array<int, string>
     */
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ImportExportController::class,
    ];

    /**
     * Path to the list behavior configuration file.
     *
     * @var string
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Path to the export behavior configuration file.
     *
     * @var string
     */
    public $exportConfig = 'config_export.yaml';

    /**
     * Required permission codes to access this controller.
     *
     * @var array<int, string>
     */
    public $requiredPermissions = ['logingrupa.colorclassifier.manage'];

    /**
     * Initialise the controller and register plugin-specific assets.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Logingrupa.ColorClassifier', 'colorclassifier');

        $this->addCss('/plugins/logingrupa/colorclassifier/assets/css/matrix.css');
        $this->addJs('/plugins/logingrupa/colorclassifier/assets/js/matrix.js');
    }

    /**
     * AJAX handler — process all offers regardless of prior processing state.
     *
     * Re-processes every offer fetched from CommerceMlParser. Existing
     * ColorEntry records are updated via updateOrCreate.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onProcessAll(): array
    {
        $batchProcessor = new BatchProcessor();
        $result         = $batchProcessor->processAll();

        Flash::success(
            "Processed {$result['processed']}, skipped {$result['skipped']}, "
            . "failed {$result['failed']} of {$result['total']} offers."
        );

        return $this->listRefresh();
    }

    /**
     * AJAX handler — process only offers not yet in the database.
     *
     * Skips offers that already have a ColorEntry record, allowing
     * incremental processing as new offers are added to the XML feed.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onProcessNew(): array
    {
        $batchProcessor = new BatchProcessor();
        $result         = $batchProcessor->processNew();

        Flash::success(
            "Processed {$result['processed']}, skipped {$result['skipped']}, "
            . "failed {$result['failed']} of {$result['total']} offers."
        );

        return $this->listRefresh();
    }
}
