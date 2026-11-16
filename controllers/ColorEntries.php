<?php

namespace Logingrupa\ColorClassifier\Controllers;

use Backend;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;
use Logingrupa\ColorClassifier\Classes\BatchProcessor;
use Logingrupa\ColorClassifier\Classes\ColorEntryOverrideService;
use Logingrupa\ColorClassifier\Controllers\Concerns\BatchProcessingHandlers;
use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\Settings;

/**
 * ColorEntries Backend Controller — manages the color classification list view.
 *
 * Provides a backend list of processed color entries with Process All,
 * Process New, Export, override/lock bulk actions and an update form for one
 * entry. Uses OctoberCMS ListController, FormController and
 * ImportExportController behaviors.
 *
 * @package Logingrupa\ColorClassifier\Controllers
 */
class ColorEntries extends Controller
{
    use BatchProcessingHandlers;

    /**
     * Implemented controller behaviors.
     *
     * @var array<int, string>
     */
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ImportExportController::class,
    ];

    /**
     * Path to the form behavior configuration file.
     *
     * @var string
     */
    public $formConfig = 'config_form.yaml';

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

        BackendMenu::setContext('Logingrupa.ColorClassifier', 'colorclassifier', 'entries');

        $this->addCss('/plugins/logingrupa/colorclassifier/assets/css/matrix.css');
        $this->addJs('/plugins/logingrupa/colorclassifier/assets/js/matrix.js');
    }

    /**
     * AJAX handler — re-process the checked entries that are not locked.
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
        $skippedLocked = 0;

        foreach ($entries as $entry) {
            if ($entry->is_locked) {
                $skippedLocked++;
                continue;
            }

            $result = $batchProcessor->reprocessEntry($entry);
            $result ? $processed++ : $failed++;
        }

        $sMessage = "Re-processed {$processed} of " . count($selectedIds) . ' entries, skipped ' . $skippedLocked . ' locked';
        Flash::success($sMessage . ($failed ? ", {$failed} failed." : '.'));

        return $this->listRefresh();
    }

    /**
     * AJAX handler — lock the checked entries so Process All skips them.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onLockSelected(): array
    {
        $arEntries = (new ColorEntryOverrideService())->lockEntries(post('checked', []));

        Flash::success('Locked ' . count($arEntries) . ' entries.');

        return $this->listRefresh();
    }

    /**
     * AJAX handler — unlock the checked entries and keep their overrides.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onUnlockSelected(): array
    {
        $arEntries = (new ColorEntryOverrideService())->unlockEntries(post('checked', []));

        Flash::success('Unlocked ' . count($arEntries) . ' entries.');

        return $this->listRefresh();
    }

    /**
     * AJAX handler — clear overrides and unlock the checked entries.
     *
     * @return array<string, mixed> List refresh response.
     */
    public function onResetSelected(): array
    {
        $arEntries = (new ColorEntryOverrideService())->resetEntries(post('checked', []));

        Flash::success('Reset ' . count($arEntries) . ' entries to the classifier result.');

        return $this->listRefresh();
    }

    /**
     * AJAX handler on the update page — reset this entry to the classifier result.
     *
     * @param int|string $recordId Entry id from the page URL.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update_onResetToAuto($recordId)
    {
        (new ColorEntryOverrideService())->resetEntries([$recordId]);

        Flash::success('Entry reset to the classifier result.');

        return Backend::redirect('logingrupa/colorclassifier/colorentries/update/' . (int) $recordId);
    }

    /**
     * Form hook — normalize the posted override just before the model is saved.
     *
     * The form fills the posted values after this hook runs, so the rules
     * run on the model's own beforeSave event, after that fill.
     *
     * @param ColorEntry $obModel Entry being saved.
     *
     * @return void
     */
    public function formBeforeSave($obModel)
    {
        $obModel->bindEvent('model.beforeSave', function () use ($obModel): void {
            (new ColorEntryOverrideService())->applyToEntry(
                $obModel,
                (string) $obModel->family_override,
                (string) $obModel->hex_override,
                (bool) $obModel->is_locked
            );
        });
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
        $obSettings->reload();

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
