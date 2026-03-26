<?php

namespace Logingrupa\ColorClassifier\Controllers;

use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;
use Logingrupa\ColorClassifier\Classes\BatchProcessor;
use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\Settings;

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
     * Path to the import/export behavior configuration file.
     *
     * @var string
     */
    public $importExportConfig = 'config_export.yaml';

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

    /**
     * AJAX handler — re-process only the checked entries.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onReprocessSelected(): array
    {
        $selectedIds = post('checked', []);

        if (empty($selectedIds)) {
            Flash::warning('No entries selected.');
            return $this->listRefresh();
        }

        $entries = ColorEntry::whereIn('id', $selectedIds)->get();
        $batchProcessor = new BatchProcessor();

        $processed = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $result = $batchProcessor->reprocessEntry($entry);
            $result ? $processed++ : $failed++;
        }

        Flash::success("Re-processed {$processed} of " . count($selectedIds) . " entries" . ($failed ? ", {$failed} failed." : "."));

        return $this->listRefresh();
    }

    /**
     * AJAX handler — open the Settings popup with a form widget bound to the Settings model.
     *
     * Builds a Form widget config by starting from the model's field config object
     * (a stdClass with a tabs property), then augments it with model and arrayName
     * before passing it to makeWidget(). This mirrors the pattern used by the
     * system Settings controller and ensures tabs/fields are resolved correctly.
     *
     * @return string Rendered popup partial HTML.
     */
    public function onLoadSettingsPopup(): string
    {
        $obSettings = Settings::instance();
        $obSettings->resetDefault();

        $obConfig             = $obSettings->getFieldConfig();
        $obConfig->model      = $obSettings;
        $obConfig->arrayName  = 'Settings';

        $obFormWidget = $this->makeWidget(Form::class, $obConfig);
        $obFormWidget->bindToController();

        return $this->makePartial('settings_popup', ['settingsFormWidget' => $obFormWidget]);
    }

    /**
     * AJAX handler — persist submitted settings form data to system_settings.
     *
     * Fills the Settings model from the posted 'Settings' form array and saves
     * it to the system_settings table. Flashes a success message on completion.
     *
     * @return void
     */
    public function onSaveSettings(): void
    {
        $obSettings = Settings::instance();
        $obSettings->fill(post('Settings', []));
        $obSettings->save();

        Flash::success('Settings saved successfully.');
    }

    /**
     * AJAX handler — delete the checked entries.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onDeleteSelected(): array
    {
        $selectedIds = post('checked', []);

        if (empty($selectedIds)) {
            Flash::warning('No entries selected.');
            return $this->listRefresh();
        }

        $deletedCount = ColorEntry::whereIn('id', $selectedIds)->delete();

        Flash::success("Deleted {$deletedCount} entries.");

        return $this->listRefresh();
    }
}
