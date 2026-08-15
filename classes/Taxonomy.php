<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * Taxonomy — Gel polish color classification taxonomy constants.
 *
 * Provides static arrays for all classification dimensions used
 * when categorizing gel polish colors. These constants define the
 * controlled vocabulary for color families, undertones, depths,
 * saturations, finishes, and opacities.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class Taxonomy
{
    /**
     * Color family names — 22 distinct color families for gel polish.
     *
     * @var array<int, string>
     */
    public static array $colorFamilies = [
        'Red',
        'Orange',
        'Coral',
        'Peach',
        'Yellow',
        'Gold',
        'Green',
        'Teal',
        'Cyan',
        'Blue',
        'Navy',
        'Indigo',
        'Violet',
        'Purple',
        'Magenta',
        'Pink',
        'Rose',
        'Brown',
        'Beige',
        'Nude',
        'Grey',
        'White',
        'Black',
    ];

    /**
     * Per-family metadata for external consumers: URL-stable slug, localized
     * display names, per-locale synonym lists for search matching, and a
     * canonical representative hex for swatches. Keyed by the family name
     * used in $colorFamilies and on ColorEntry taxonomy (DRY: one source,
     * never per entry). Slugs are a STABLE contract - external stores build
     * URLs and filter caches on them.
     *
     * @var array<string, array{slug: string, hex: string, names: array<string, string>, synonyms: array<string, array<int, string>>}>
     */
    public static array $familyMeta = [
        'Red' => [
            'slug' => 'red',
            'hex' => '#d32f2f',
            'names' => ['en' => 'Red', 'lv' => 'Sarkans', 'ru' => 'Красный'],
            'synonyms' => [
                'en' => ['crimson', 'scarlet'],
                'lv' => ['sarkana', 'sarkani', 'sarkanas', 'sarkanā'],
                'ru' => ['красная', 'красное', 'krasnyj', 'krasnaja'],
            ],
        ],
        'Orange' => [
            'slug' => 'orange',
            'hex' => '#f57c00',
            'names' => ['en' => 'Orange', 'lv' => 'Oranžs', 'ru' => 'Оранжевый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['oranža', 'oranži', 'oranžas'],
                'ru' => ['оранжевая', 'oranzhevyj'],
            ],
        ],
        'Coral' => [
            'slug' => 'coral',
            'hex' => '#ff6f61',
            'names' => ['en' => 'Coral', 'lv' => 'Koraļļu', 'ru' => 'Коралловый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['koralis', 'koraļi', 'koraļu'],
                'ru' => ['коралловая', 'korallovyj'],
            ],
        ],
        'Peach' => [
            'slug' => 'peach',
            'hex' => '#ffcba4',
            'names' => ['en' => 'Peach', 'lv' => 'Persiku', 'ru' => 'Персиковый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['persiks', 'persika'],
                'ru' => ['персиковая', 'persikovyj'],
            ],
        ],
        'Yellow' => [
            'slug' => 'yellow',
            'hex' => '#fdd835',
            'names' => ['en' => 'Yellow', 'lv' => 'Dzeltens', 'ru' => 'Жёлтый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['dzeltena', 'dzelteni', 'dzeltenas'],
                'ru' => ['желтый', 'желтая', 'zheltyj'],
            ],
        ],
        'Gold' => [
            'slug' => 'gold',
            'hex' => '#d4af37',
            'names' => ['en' => 'Gold', 'lv' => 'Zelta', 'ru' => 'Золотой'],
            'synonyms' => [
                'en' => ['golden'],
                'lv' => ['zeltains', 'zeltaina', 'zelts'],
                'ru' => ['золотистый', 'золотая', 'zolotoj'],
            ],
        ],
        'Green' => [
            'slug' => 'green',
            'hex' => '#43a047',
            'names' => ['en' => 'Green', 'lv' => 'Zaļš', 'ru' => 'Зелёный'],
            'synonyms' => [
                'en' => [],
                'lv' => ['zaļa', 'zaļi', 'zaļas'],
                'ru' => ['зеленый', 'зеленая', 'zelenyj'],
            ],
        ],
        'Teal' => [
            'slug' => 'teal',
            'hex' => '#00897b',
            'names' => ['en' => 'Teal', 'lv' => 'Zilganzaļš', 'ru' => 'Сине-зелёный'],
            'synonyms' => [
                'en' => [],
                'lv' => ['zilganzaļa', 'zilgani zaļš'],
                'ru' => ['сине-зеленый', 'sine-zelenyj'],
            ],
        ],
        'Cyan' => [
            'slug' => 'cyan',
            'hex' => '#00acc1',
            'names' => ['en' => 'Cyan', 'lv' => 'Tirkīzs', 'ru' => 'Бирюзовый'],
            'synonyms' => [
                'en' => ['turquoise', 'aqua'],
                'lv' => ['tirkīza', 'tirkīzzils', 'ciāns'],
                'ru' => ['бирюзовая', 'циан', 'birjuzovyj'],
            ],
        ],
        'Blue' => [
            'slug' => 'blue',
            'hex' => '#1e88e5',
            'names' => ['en' => 'Blue', 'lv' => 'Zils', 'ru' => 'Синий'],
            'synonyms' => [
                'en' => [],
                'lv' => ['zila', 'zili', 'zilas', 'debeszils'],
                'ru' => ['синяя', 'голубой', 'голубая', 'sinij', 'goluboj'],
            ],
        ],
        'Navy' => [
            'slug' => 'navy',
            'hex' => '#1a237e',
            'names' => ['en' => 'Navy', 'lv' => 'Tumši zils', 'ru' => 'Тёмно-синий'],
            'synonyms' => [
                'en' => ['navy blue'],
                'lv' => ['tumši zila', 'jūras zils'],
                'ru' => ['темно-синий', 'темно-синяя', 'temno-sinij'],
            ],
        ],
        'Indigo' => [
            'slug' => 'indigo',
            'hex' => '#3f51b5',
            'names' => ['en' => 'Indigo', 'lv' => 'Indigo', 'ru' => 'Индиго'],
            'synonyms' => [
                'en' => [],
                'lv' => [],
                'ru' => [],
            ],
        ],
        'Violet' => [
            'slug' => 'violet',
            'hex' => '#8e24aa',
            'names' => ['en' => 'Violet', 'lv' => 'Violets', 'ru' => 'Фиолетовый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['violeta', 'violeti', 'violetas'],
                'ru' => ['фиолетовая', 'fioletovyj'],
            ],
        ],
        'Purple' => [
            'slug' => 'purple',
            'hex' => '#6a1b9a',
            'names' => ['en' => 'Purple', 'lv' => 'Purpurs', 'ru' => 'Пурпурный'],
            'synonyms' => [
                'en' => [],
                'lv' => ['purpura', 'lillā', 'lilla', 'purpurviolets'],
                'ru' => ['пурпурная', 'сиреневый', 'лиловый', 'purpurnyj'],
            ],
        ],
        'Magenta' => [
            'slug' => 'magenta',
            'hex' => '#d81b60',
            'names' => ['en' => 'Magenta', 'lv' => 'Fuksija', 'ru' => 'Маджента'],
            'synonyms' => [
                'en' => ['fuchsia'],
                'lv' => ['fuksijas', 'madženta'],
                'ru' => ['фуксия', 'fuksija'],
            ],
        ],
        'Pink' => [
            'slug' => 'pink',
            'hex' => '#f06292',
            'names' => ['en' => 'Pink', 'lv' => 'Rozā', 'ru' => 'Розовый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['roza', 'rozīgs', 'rozīga'],
                'ru' => ['розовая', 'rozovyj'],
            ],
        ],
        'Rose' => [
            'slug' => 'rose',
            'hex' => '#c94f7c',
            'names' => ['en' => 'Rose', 'lv' => 'Rožu', 'ru' => 'Роза'],
            'synonyms' => [
                'en' => [],
                'lv' => ['roze', 'rozes'],
                'ru' => ['розы', 'roza'],
            ],
        ],
        'Brown' => [
            'slug' => 'brown',
            'hex' => '#795548',
            'names' => ['en' => 'Brown', 'lv' => 'Brūns', 'ru' => 'Коричневый'],
            'synonyms' => [
                'en' => ['chocolate'],
                'lv' => ['brūna', 'brūni', 'brūnas', 'šokolādes'],
                'ru' => ['коричневая', 'шоколадный', 'korichnevyj'],
            ],
        ],
        'Beige' => [
            'slug' => 'beige',
            'hex' => '#d7c4a3',
            'names' => ['en' => 'Beige', 'lv' => 'Bēšs', 'ru' => 'Бежевый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['bēša', 'beža', 'bešs', 'smilškrāsa', 'smilšu'],
                'ru' => ['бежевая', 'беж', 'bezhevyj'],
            ],
        ],
        'Nude' => [
            'slug' => 'nude',
            'hex' => '#e3bc9a',
            'names' => ['en' => 'Nude', 'lv' => 'Nude', 'ru' => 'Нюд'],
            'synonyms' => [
                'en' => [],
                'lv' => ['miesas krāsa', 'miesas', 'miesaskrāsas'],
                'ru' => ['нюдовый', 'телесный', 'телесная', 'njud', 'telesnyj'],
            ],
        ],
        'Grey' => [
            'slug' => 'grey',
            'hex' => '#9e9e9e',
            'names' => ['en' => 'Grey', 'lv' => 'Pelēks', 'ru' => 'Серый'],
            'synonyms' => [
                'en' => ['gray'],
                'lv' => ['pelēka', 'pelēki', 'pelēkas'],
                'ru' => ['серая', 'seryj'],
            ],
        ],
        'White' => [
            'slug' => 'white',
            'hex' => '#fafafa',
            'names' => ['en' => 'White', 'lv' => 'Balts', 'ru' => 'Белый'],
            'synonyms' => [
                'en' => [],
                'lv' => ['balta', 'balti', 'baltas'],
                'ru' => ['белая', 'belyj'],
            ],
        ],
        'Black' => [
            'slug' => 'black',
            'hex' => '#212121',
            'names' => ['en' => 'Black', 'lv' => 'Melns', 'ru' => 'Чёрный'],
            'synonyms' => [
                'en' => [],
                'lv' => ['melna', 'melni', 'melnas'],
                'ru' => ['черный', 'черная', 'chernyj'],
            ],
        ],
    ];

    /**
     * Undertone values — warm, cool, or neutral bias in a color.
     *
     * @var array<int, string>
     */
    public static array $undertones = [
        'Warm',
        'Cool',
        'Neutral',
    ];

    /**
     * Depth values — lightness level from very light to very dark.
     *
     * @var array<int, string>
     */
    public static array $depths = [
        'Very Light',
        'Light',
        'Medium',
        'Dark',
        'Very Dark',
    ];

    /**
     * Saturation values — chroma intensity from muted to neon.
     *
     * @var array<int, string>
     */
    public static array $saturations = [
        'Muted',
        'Soft',
        'Medium',
        'Vivid',
        'Neon',
    ];

    /**
     * Finish values — surface texture/effect of the gel polish.
     *
     * Finish cannot be detected from image color analysis alone;
     * it is stored as null by default and must be set manually.
     *
     * @var array<int, string>
     */
    public static array $finishes = [
        'Cream',
        'Shimmer',
        'Glitter',
        'Metallic',
        'Chrome',
        'Matte',
        'Glossy',
        'Foil',
        'Holographic',
        'Cat Eye',
    ];

    /**
     * Opacity values — translucency level from sheer to fully opaque.
     *
     * @var array<int, string>
     */
    public static array $opacities = [
        'Sheer',
        'Semi-Sheer',
        'Semi-Opaque',
        'Opaque',
    ];
}
