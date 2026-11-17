<?php

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;

/**
 * Migration to give the 1C collections their swatch gradients from
 * data/collection_gradients.php. Only collection rows with a listed slug
 * change, so a database without those collections is left alone.
 */
class SeedCollectionGradients extends Migration
{
    private const TABLE = 'logingrupa_colorclassifier_color_families';

    private const KIND_COLLECTION = 'collection';

    /** @var array<int, string> DISCO's three left to right stops before this migration. */
    private const PREVIOUS_DISCO_STOPS = ['#a855f7', '#ec4899', '#22d3ee'];

    /**
     * Run the migration - write type, angle and stops on every listed collection.
     *
     * @return void
     */
    public function up(): void
    {
        foreach ($this->loadGradients() as $sSlug => $arGradient) {
            $this->writeGradient($sSlug, $arGradient['stops'], $arGradient['type'], $arGradient['angle']);
        }
    }

    /**
     * Reverse the migration - clear the listed collections and give DISCO
     * back its old stops, shapes the pre-type code still reads.
     *
     * @return void
     */
    public function down(): void
    {
        foreach (array_keys($this->loadGradients()) as $sSlug) {
            $this->writeGradient($sSlug, null, 'linear', 90);
        }

        $this->writeGradient('disco', self::PREVIOUS_DISCO_STOPS, 'linear', 90);
    }

    /**
     * @param array<int, string>|null $arStops
     *
     * @return void
     */
    private function writeGradient(string $sSlug, ?array $arStops, string $sType, int $iAngle): void
    {
        DB::table(self::TABLE)
            ->where('slug', $sSlug)
            ->where('kind', self::KIND_COLLECTION)
            ->update([
                'gradient'       => $arStops === null ? null : json_encode($arStops),
                'gradient_type'  => $sType,
                'gradient_angle' => $iAngle,
                'updated_at'     => now(),
            ]);
    }

    /**
     * @return array<string, array{type: string, angle: int, stops: array<int, string>}>
     */
    private function loadGradients(): array
    {
        return require __DIR__ . '/data/collection_gradients.php';
    }
}
