<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Groups the free-text `orders.shipping_city` values into real cities, so a
 * filter on "Riyadh" also matches "riyadh", "RIYADH", "Ar-Riyadh", "الرياض",
 * "Riyadh City", etc.
 *
 * Every raw value is reduced to a normalised key (case, spacing, punctuation,
 * Arabic letter variants, leading "Al"/"ال", noise words like "city" or
 * "Saudi Arabia"). Keys are then linked into groups through the curated
 * aliases below plus the J&T English/Arabic name pairs. A value whose key is
 * in no group still groups with its own case/spacing variants.
 */
class CityDirectory
{
    /**
     * Canonical name => extra spellings for the cities orders mostly ship to.
     * These take priority over the J&T list for the displayed name.
     */
    protected const ALIASES = [
        'Riyadh' => ['Riyad', 'Riadh', 'Ar Riyadh', 'Al Riyadh', 'Alriyadh', 'Arriyadh', 'الرياض', 'رياض'],
        'Jeddah' => ['Jedda', 'Jiddah', 'Jidda', 'Jaddah', 'Djeddah', 'جدة', 'جده'],
        'Makkah' => ['Mecca', 'Makka', 'Mekkah', 'Mekka', 'Makkah Al Mukarramah', 'Mecca Al Mukarramah', 'مكة', 'مكة المكرمة'],
        'Madinah' => ['Medina', 'Madina', 'Medinah', 'Al Madinah Al Munawwarah', 'Madinah Al Munawwarah', 'المدينة', 'المدينة المنورة'],
        'Dammam' => ['Damam', 'Ad Dammam', 'Al Dammam', 'الدمام'],
        'Khobar' => ['Al Khobar', 'Alkhobar', 'Al Khubar', 'Khubar', 'الخبر'],
        'Dhahran' => ['Dahran', 'Az Zahran', 'الظهران'],
        'Taif' => ['Al Taif', 'At Taif', 'Altaif', "Ta'if", 'الطائف'],
        'Tabuk' => ['Tabouk', 'تبوك'],
        'Buraydah' => ['Buraidah', 'Buraida', 'Burayda', 'Breda', 'بريدة'],
        'Unaizah' => ['Unayzah', 'Onaizah', 'Onaiza', 'Anaiza', 'عنيزة'],
        'Khamis Mushait' => ['Khamis Mushayt', 'Khamis Mushyt', 'Khamis Mushait City', 'خميس مشيط'],
        'Abha' => ['أبها'],
        'Hail' => ["Ha'il", 'Hael', 'Hayel', 'حائل'],
        'Hafar Al Batin' => ['Hafr Al Batin', 'Hafer Al Batin', 'Hafar Albatin', 'Hafr Albatin', 'حفر الباطن'],
        'Jubail' => ['Al Jubail', 'Jubayl', 'Al Jubayl', 'الجبيل'],
        'Al Kharj' => ['Kharj', 'الخرج'],
        'Qatif' => ['Al Qatif', 'Katif', 'القطيف'],
        'Al Ahsa' => ['Al Hasa', 'Ahsa', 'Hasa', 'Al Ahsaa', 'Alahsa', 'الأحساء'],
        'Hofuf' => ['Al Hofuf', 'Hufuf', 'Al Hufuf', 'الهفوف'],
        'Mubarraz' => ['Al Mubarraz', 'المبرز'],
        'Najran' => ['نجران'],
        'Jazan' => ['Jizan', 'Gizan', 'Jazan City', 'جازان', 'جيزان'],
        'Yanbu' => ['Yanbu Al Bahr', 'Yanbo', 'Yenbo', 'ينبع'],
        'Sakaka' => ['Sakakah', 'سكاكا'],
        'Arar' => ['عرعر'],
        'Ar Rass' => ['Rass', 'Al Rass', 'الرس'],
        'Al Bahah' => ['Al Baha', 'Baha', 'Bahah', 'الباحة'],
        'Bisha' => ['Bishah', 'بيشة'],
        'Al Qurayyat' => ['Qurayyat', 'Gurayat', 'Al Qurayat', 'القريات'],
        'Diriyah' => ['Ad Diriyah', 'Al Diriyah', "Dir'iyah", 'الدرعية'],
        'Al Majmaah' => ['Majmaah', 'المجمعة'],
        'Az Zulfi' => ['Zulfi', 'Al Zulfi', 'الزلفي'],
        'Ad Dawadmi' => ['Dawadmi', 'Duwadimi', 'Ad Duwadimi', 'الدوادمي', 'دوادمي'],
        'Rabigh' => ['رابغ'],
        'Al Ula' => ['AlUla', 'Ula', 'العلا'],
        'Ras Tanura' => ['Ras Tanurah', 'رأس تنورة'],
        'Abqaiq' => ['Buqayq', 'بقيق', 'ابقيق'],
        'Khafji' => ['Al Khafji', 'الخفجي'],
        'Sabya' => ['Sabia', 'صبيا'],
    ];

    /** Words that describe rather than name a place; dropped unless nothing else remains. */
    protected const NOISE = [
        'kingdom of saudi arabia', 'saudi arabia', 'ksa', 'city', 'region', 'province', 'governorate',
        'المملكه العربيه السعوديه', 'السعوديه', 'مدينه', 'منطقه', 'محافظه',
    ];

    /** @var array<string, array{name: string, ar: ?string}>|null normalised key => group */
    protected ?array $index = null;

    /**
     * Normalise a raw city string to a comparison key.
     */
    public static function key(?string $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        // Arabic: drop diacritics/tatweel and fold letter variants that people type interchangeably.
        $v = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $v);
        $v = strtr($v, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        // Apostrophes join rather than split ("Ha'il" → "hail"); other punctuation, and
        // standalone numbers such as postcodes, become spaces.
        $v = preg_replace("/['’`ʼ]/u", '', $v);
        $v = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $v);
        $v = preg_replace('/(?<!\S)\p{N}+(?!\S)/u', ' ', $v);
        $v = self::squash($v);

        foreach (self::NOISE as $word) {
            $stripped = self::squash(preg_replace('/(?<!\S)'.preg_quote($word, '/').'(?!\S)/u', ' ', $v));
            if ($stripped !== '') {
                $v = $stripped;
            }
        }

        // Leading definite article: "Al Khobar" / "Ar-Riyadh" / "الرياض".
        $v = preg_replace('/^(al|el|ar|ad|as|at|az|an|ash|adh|ath) (?=\S)/u', '', $v);
        $v = preg_replace('/^ال(?=\p{L}{2})/u', '', $v);

        return $v;
    }

    protected static function squash(string $v): string
    {
        return trim(preg_replace('/\s+/u', ' ', $v));
    }

    /**
     * The id a raw value is grouped under: the canonical city name when it is a
     * known city, otherwise its normalised key. '' means "not a city" (blank, "-", numbers).
     */
    public function groupId(?string $value): string
    {
        $key = self::key($value);

        if ($key === '') {
            return '';
        }

        return $this->index()[$key]['name'] ?? $key;
    }

    /**
     * Filter options built from the cities actually present on orders, most
     * orders first. `keywords` carries every stored spelling so the UI search
     * finds a city by any of them.
     *
     * @return array<int, array{value: string, label: string, ar: ?string, count: int, keywords: array<int, string>}>
     */
    public function options(): array
    {
        $groups = [];

        foreach ($this->distinctCities() as $raw => $count) {
            $id = $this->groupId($raw);
            if ($id === '') {
                continue;
            }

            $group = &$groups[$id];
            $group['count'] = ($group['count'] ?? 0) + $count;
            $group['variants'][trim($raw)] = ($group['variants'][trim($raw)] ?? 0) + $count;
            unset($group);
        }

        $index = $this->index();
        $options = [];

        foreach ($groups as $id => $group) {
            arsort($group['variants']);
            $known = $index[self::key($id)] ?? null;

            $options[] = [
                'value' => $id,
                // Unknown cities are labelled with their most common spelling.
                'label' => $known['name'] ?? array_key_first($group['variants']),
                'ar' => $known['ar'] ?? null,
                'count' => $group['count'],
                'keywords' => array_keys($group['variants']),
            ];
        }

        usort($options, fn ($a, $b) => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);

        return $options;
    }

    /**
     * Every stored shipping_city spelling that belongs to one of the given groups.
     *
     * @param  array<int, string>  $selected  group ids (or any spelling of the city)
     * @return array<int, string>
     */
    public function rawValuesFor(array $selected): array
    {
        $wanted = array_flip(array_filter(array_map(fn ($s) => $this->groupId($s), $selected)));

        if (empty($wanted)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($this->distinctCities()),
            fn ($raw) => isset($wanted[$this->groupId($raw)])
        ));
    }

    /**
     * @return array<string, int> raw shipping_city => order count
     */
    protected function distinctCities(): array
    {
        return DB::table('orders')
            ->select('shipping_city', DB::raw('count(*) as count'))
            ->whereNotNull('shipping_city')
            ->groupBy('shipping_city')
            ->pluck('count', 'shipping_city')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * Normalised key => group, built by linking every spelling that shares a key.
     *
     * @return array<string, array{name: string, ar: ?string}>
     */
    protected function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        // Union-find over normalised keys; each entry is a list of spellings of one city.
        $parent = [];
        $find = function (string $k) use (&$parent, &$find): string {
            if (! isset($parent[$k])) {
                $parent[$k] = $k;
            }

            return $parent[$k] === $k ? $k : ($parent[$k] = $find($parent[$k]));
        };

        $entries = [];
        foreach (self::ALIASES as $name => $aliases) {
            $entries[] = [$name, ...$aliases];
        }
        foreach (require resource_path('data/saudi-city-names.php') as [$en, $ar]) {
            $entries[] = array_filter([$en, $ar]);
        }

        foreach ($entries as $spellings) {
            $keys = array_values(array_filter(array_map([self::class, 'key'], $spellings)));
            foreach ($keys as $k) {
                $parent[$find($k)] = $find($keys[0]);
            }
        }

        // Pick a display name per group: curated name first, otherwise the first
        // English spelling that is not written in all caps.
        $names = [];
        $arabic = [];
        foreach ($entries as $i => $spellings) {
            $root = $find(self::key(reset($spellings)));
            $curated = $i < count(self::ALIASES);

            foreach ($spellings as $s) {
                $isArabic = (bool) preg_match('/\p{Arabic}/u', $s);

                if ($isArabic) {
                    $arabic[$root] ??= $s;
                } elseif ($curated) {
                    $names[$root] ??= $s;
                } elseif (! isset($names[$root]) || $names[$root] === mb_strtoupper($names[$root])) {
                    $names[$root] = $s === mb_strtoupper($s) ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE) : $s;
                }
            }
        }

        $this->index = [];
        foreach (array_keys($parent) as $k) {
            $root = $find($k);
            $this->index[$k] = [
                'name' => $names[$root] ?? $arabic[$root],
                'ar' => $arabic[$root] ?? null,
            ];
        }

        return $this->index;
    }
}
