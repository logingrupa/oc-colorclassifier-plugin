<?php

namespace Logingrupa\ColorClassifier\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Logingrupa\ColorClassifier\Classes\ColorEntryOverrideService;
use Logingrupa\ColorClassifier\Classes\ColorExtractor;
use Logingrupa\ColorClassifier\Classes\FamilyBoardPayloadBuilder;
use Logingrupa\ColorClassifier\Classes\FamilyMembershipQuery;
use Logingrupa\ColorClassifier\Classes\FamilyMembershipService;
use Logingrupa\ColorClassifier\Classes\ImageHexSampler;
use Logingrupa\ColorClassifier\Controllers\Concerns\BatchProcessingHandlers;
use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\ColorFamily;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;

/**
 * FamilyBoard Backend Controller - offers grouped by effective family.
 *
 * Custom page (no list or form widget fits a draggable board). A primary color
 * change goes through ColorEntryOverrideService, the same path the Color entries
 * list and form use; extra colors and collections go through
 * FamilyMembershipService. Every handler validates its input before use.
 *
 * @package Logingrupa\ColorClassifier\Controllers
 */
class FamilyBoard extends Controller
{
    use BatchProcessingHandlers;

    /**
     * Required permission codes to access this controller.
     *
     * @var array<int, string>
     */
    public $requiredPermissions = ['logingrupa.colorclassifier.manage'];

    /** @var int JSON flags that keep the payload safe inside a script tag. */
    private const PAYLOAD_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Logingrupa.ColorClassifier', 'colorclassifier', 'board');

        $sAssetPath = '/plugins/logingrupa/colorclassifier/assets';
        $this->addCss($sAssetPath . '/css/matrix.css');
        $this->addCss($sAssetPath . '/css/family-board.css');
        $this->addJs($sAssetPath . '/js/matrix.js');
        $this->addJs($sAssetPath . '/js/family-board-store.js');
        $this->addJs($sAssetPath . '/js/family-board-view.js');
        $this->addJs($sAssetPath . '/js/family-board-dropdown.js');
        $this->addJs($sAssetPath . '/js/family-board-drag.js');
        $this->addJs($sAssetPath . '/js/family-board-chooser.js');
        $this->addJs($sAssetPath . '/js/family-board-drawer.js');
        $this->addJs($sAssetPath . '/js/family-board.js');
    }

    /**
     * The board page; the payload never includes the cropped image blobs.
     *
     * @return void
     */
    public function index(): void
    {
        $this->pageTitle = 'Family board';

        $arPayload = (new FamilyBoardPayloadBuilder())->build(
            ColorEntry::withoutImageData()->orderBy('id')->get(),
            ColorFamily::all(),
            (new FamilyMembershipQuery())->loadMembershipRows()
        );

        $this->vars['payloadJson'] = json_encode($arPayload, self::PAYLOAD_JSON_FLAGS);
    }

    /**
     * AJAX handler - move offers to a family and lock them.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onMoveEntries(): array
    {
        $sFamily = $this->readFamilyName('family');
        $arEntries = (new ColorEntryOverrideService())->moveEntries($this->readEntryIds(), $sFamily);

        Flash::success('Moved ' . $this->describeOffers(count($arEntries)) . ' to ' . $sFamily . ', locked.');

        return $this->mapCards($arEntries);
    }

    /**
     * AJAX handler - put offers into an extra color or a collection (a clone, or a cross-kind drop).
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onAddMemberships(): array
    {
        $arEntryIds = $this->readEntryIds();
        $sFamily = $this->readFamilyName('family');
        (new FamilyMembershipService())->addMemberships($arEntryIds, $sFamily);

        return $this->respondToMembershipChange($arEntryIds, 'Added ' . $this->describeOffers(count($arEntryIds)) . ' to ' . $sFamily . '.');
    }

    /**
     * AJAX handler - take offers out of an extra color or a collection.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onRemoveMemberships(): array
    {
        $arEntryIds = $this->readEntryIds();
        $sFamily = $this->readFamilyName('family');
        (new FamilyMembershipService())->removeMemberships($arEntryIds, $sFamily);

        return $this->respondToMembershipChange($arEntryIds, 'Removed ' . $this->describeOffers(count($arEntryIds)) . ' from ' . $sFamily . '.');
    }

    /**
     * AJAX handler - move offers from one group to another group of the same kind.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onMoveMemberships(): array
    {
        $arEntryIds = $this->readEntryIds();
        $sFromFamily = $this->readFamilyName('from');
        $sFamily = $this->readFamilyName('family');
        (new FamilyMembershipService())->moveMemberships($arEntryIds, $sFromFamily, $sFamily);

        return $this->respondToMembershipChange($arEntryIds, 'Moved ' . $this->describeOffers(count($arEntryIds)) . ' from ' . $sFromFamily . ' to ' . $sFamily . '.');
    }

    /**
     * AJAX handler - give the collection tags of offers back to 1C. An empty family means every collection.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onResetMemberships(): array
    {
        $arEntryIds = $this->readEntryIds();
        $sFamily = trim((string) post('family', ''));
        (new FamilyMembershipService())->resetToFeed($arEntryIds, $sFamily === '' ? null : $sFamily);

        return $this->respondToMembershipChange($arEntryIds, 'Reset ' . $this->describeOffers(count($arEntryIds)) . ' to 1C.');
    }

    /**
     * AJAX handler - lock offers so Process All skips them.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onLockEntries(): array
    {
        $arEntries = (new ColorEntryOverrideService())->lockEntries($this->readEntryIds());

        Flash::success('Locked ' . $this->describeOffers(count($arEntries)) . '.');

        return $this->mapCards($arEntries);
    }

    /**
     * AJAX handler - unlock offers and keep their overrides.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onUnlockEntries(): array
    {
        $arEntries = (new ColorEntryOverrideService())->unlockEntries($this->readEntryIds());

        Flash::success('Unlocked ' . $this->describeOffers(count($arEntries)) . '.');

        return $this->mapCards($arEntries);
    }

    /**
     * AJAX handler - clear overrides and unlock offers.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onResetEntries(): array
    {
        $arEntries = (new ColorEntryOverrideService())->resetEntries($this->readEntryIds());

        Flash::success('Reset ' . $this->describeOffers(count($arEntries)) . ' to the classifier result.');

        return $this->mapCards($arEntries);
    }

    /**
     * AJAX handler - save one offer from the drawer.
     *
     * An empty family or hex means "no override".
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function onSaveEntry(): array
    {
        $obEntry = (new ColorEntryOverrideService())->saveEntry(
            $this->readEntryId('id'),
            trim((string) post('family', '')),
            trim((string) post('hex', '')),
            filter_var(post('locked'), FILTER_VALIDATE_BOOLEAN)
        );

        Flash::success('Saved ' . $obEntry->variation_name . '.');

        return $this->mapCards([$obEntry]);
    }

    /**
     * AJAX handler - sample a swatch hex from the offer's own image.
     *
     * The client sends only the entry id and the click position; the URL comes
     * from the database row. Nothing is saved here.
     *
     * @return array{hex: string}
     *
     * @throws ValidationException When the id or a coordinate is invalid.
     * @throws ApplicationException When the image cannot be downloaded.
     */
    public function onSampleHex(): array
    {
        $iEntryId = $this->readEntryId('id');
        $flRelativeX = $this->readRelativeCoordinate('x');
        $flRelativeY = $this->readRelativeCoordinate('y');

        $obEntry = ColorEntry::query()->select(['id', 'image_url'])->find($iEntryId);

        if ($obEntry === null || preg_match('#^https?://#i', (string) $obEntry->image_url) !== 1) {
            throw new ValidationException(['id' => 'This offer has no downloadable image.']);
        }

        $obImage = ColorExtractor::downloadImage($obEntry->image_url);

        if ($obImage === false) {
            throw new ApplicationException('Could not download the offer image');
        }

        return ['hex' => ImageHexSampler::sampleHex($obImage, $flRelativeX, $flRelativeY)];
    }

    /**
     * @param array<int, ColorEntry> $arEntries Saved rows.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    private function mapCards(array $arEntries): array
    {
        return ['cards' => (new FamilyBoardPayloadBuilder())->mapCards(
            $arEntries,
            ColorFamily::all(),
            (new FamilyMembershipQuery())->loadMembershipRows()
        )];
    }

    /**
     * @param array<int, mixed> $arEntryIds Ids the service has already validated.
     *
     * @return array{cards: array<int, array<string, mixed>>}
     */
    private function respondToMembershipChange(array $arEntryIds, string $sMessage): array
    {
        Flash::success($sMessage);

        return $this->mapCards(ColorEntry::withoutImageData()->whereIn('id', array_map('intval', $arEntryIds))->orderBy('id')->get()->all());
    }

    /**
     * @return array<int, mixed> The posted ids list; the service validates each id.
     *
     * @throws ValidationException When ids is not a list.
     */
    private function readEntryIds(): array
    {
        $arEntryIds = post('ids');

        if (!is_array($arEntryIds)) {
            throw new ValidationException(['ids' => 'Select at least one offer.']);
        }

        return array_values($arEntryIds);
    }

    /**
     * @throws ValidationException When the field is not a positive integer.
     */
    private function readEntryId(string $sField): int
    {
        $mEntryId = filter_var(post($sField), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($mEntryId === false) {
            throw new ValidationException([$sField => 'The offer id must be a positive integer.']);
        }

        return $mEntryId;
    }

    /**
     * @throws ValidationException When the family is empty; the service checks it is active.
     */
    private function readFamilyName(string $sField): string
    {
        $sFamily = trim((string) post($sField, ''));

        if ($sFamily === '') {
            throw new ValidationException([$sField => 'Choose a family.']);
        }

        return $sFamily;
    }

    /**
     * @throws ValidationException When the value is not a number from 0 to 1.
     */
    private function readRelativeCoordinate(string $sField): float
    {
        $mValue = post($sField);

        if (!is_numeric($mValue) || (float) $mValue < 0.0 || (float) $mValue > 1.0) {
            throw new ValidationException([$sField => 'The position must be a number between 0 and 1.']);
        }

        return (float) $mValue;
    }

    private function describeOffers(int $iCount): string
    {
        return $iCount . ($iCount === 1 ? ' offer' : ' offers');
    }
}
