<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Launch catalog structure — the 8 flat categories and their agreed
 * parameters/options from `.claude/docs/catalog/categories.md` (the source
 * of truth; update that file first, then this seeder). Real reference data,
 * safe for every environment, and idempotent: categories are keyed on the
 * English slug, parameters on (category, English name), options on
 * (parameter, English value), so re-running only fills in what's missing
 * and never duplicates rows or overwrites admin edits to other fields.
 *
 * Not modelled yet (no columns exist): stable parameter `code`, quick-pick
 * suggestions, form row groups, conditional "required if". The free/paid flag
 * is `requires_subscription` (only Ribbons are free). Barcode scanners and PDAs are still "to
 * discuss" in the spec, so they get a small provisional parameter set.
 *
 * Translations: a plain string is used as-is in every locale (technical
 * values like "Wax", "203 dpi", "IN"); an array is [en, ru, uz].
 */
class CatalogSeeder extends Seeder
{
    private const YES_NO = [['Yes', 'Да', 'Ha'], ['No', 'Нет', "Yo'q"]];

    private const MODULE = [
        ['Built-in', 'Встроенный', "O'rnatilgan"],
        ['Optional (sold separately)', 'Опция (продаётся отдельно)', 'Ixtiyoriy (alohida sotiladi)'],
        ['Not available', 'Нет', 'Mavjud emas'],
    ];

    private const PRINTHEAD = [['Flat head', 'Плоская головка', 'Yassi kallak'], ['Near-edge', 'Near-edge', 'Near-edge']];

    public function run(): void
    {
        foreach ($this->catalog() as $sortOrder => $category) {
            $model = $this->category($category['name'], $sortOrder + 1, ! ($category['free'] ?? false), $category['icon'] ?? null);

            foreach ($category['parameters'] as $paramOrder => $parameter) {
                $this->parameter($model, $parameter, $paramOrder + 1);
            }
        }

        $this->command?->info('Catalog structure seeded: '.count($this->catalog()).' categories.');
    }

    /**
     * Parameter shorthand: [name, type, required, filterable, unit|options].
     *
     * @return array<int, array{name: array<int, string>, parameters: array<int, array<int, mixed>>}>
     */
    private function catalog(): array
    {
        $printer = [
            [['Resolution', 'Разрешение', 'Rezolyutsiya'], 'select_single', true, true, ['203 dpi', '300 dpi', '600 dpi']],
            [['Max print width', 'Макс. ширина печати', 'Maks. bosma kengligi'], 'number', true, true, 'mm'],
            [['Ribbon: max width', 'Риббон: макс. ширина', 'Lenta: maks. kenglik'], 'number', true, false, 'mm'],
            [['Ribbon: max length', 'Риббон: макс. длина', 'Lenta: maks. uzunlik'], 'number', true, true, 'm'],
            [['Ribbon: core', 'Риббон: втулка', 'Lenta: vtulka'], 'select_multiple', true, true, ['0.5"', '1"']],
            [['Ribbon: ink side', 'Риббон: сторона краски', "Lenta: bo'yoq tomoni"], 'select_multiple', true, true, ['IN', 'OUT']],
            [['Printhead type', 'Тип печатающей головки', 'Bosma kallak turi'], 'select_single', true, true, self::PRINTHEAD],
            [['Interfaces', 'Интерфейсы', 'Interfeyslar'], 'select_multiple', true, true, ['USB', 'Ethernet', 'Wi-Fi', 'Bluetooth', 'RS-232', 'USB host']],
            [['Cutter', 'Отрезчик', 'Kesgich'], 'select_single', true, true, self::MODULE],
            [['Peeler', 'Отделитель', 'Ajratgich'], 'select_single', true, true, self::MODULE],
            [['Rewinder', 'Подмотчик', "O'ragich"], 'select_single', true, true, self::MODULE],
            [['Print speed', 'Скорость печати', 'Bosma tezligi'], 'number', false, true, 'mm/s'],
            [['Max media roll OD', 'Макс. диаметр рулона', 'Maks. rulon diametri'], 'number', false, false, 'mm'],
            [['Media core', 'Втулка носителя', 'Material vtulkasi'], 'select_multiple', false, true, ['25 mm', '40 mm', '76 mm']],
            ['RFID', 'select_single', false, true, [['Built-in', 'Встроенный', "O'rnatilgan"], ['Optional', 'Опция', 'Ixtiyoriy'], ['Not available', 'Нет', 'Mavjud emas']]],
            [['Printer language / emulation', 'Язык принтера / эмуляция', 'Printer tili / emulyatsiya'], 'select_multiple', false, true, ['ZPL', 'EPL', 'DPL', 'TSPL', 'IPL', ['Other', 'Другое', 'Boshqa']]],
        ];

        return [
            [
                'name' => ['Thermal transfer ribbons', 'Термотрансферные риббоны', 'Termotransfer lentalar'],
                'icon' => 'ribbon-roll',
                'free' => true,
                'parameters' => [
                    [['Type', 'Тип', 'Turi'], 'select_single', true, true, ['Wax', 'Wax-resin', 'Resin'], CategoryParameter::CODE_RIBBON_TYPE],
                    [['Width', 'Ширина', 'Kengligi'], 'number', true, true, 'mm', CategoryParameter::CODE_RIBBON_WIDTH],
                    [['Length', 'Длина', 'Uzunligi'], 'number', true, true, 'm'],
                    [['Ink side', 'Сторона краски', "Bo'yoq tomoni"], 'select_single', true, true, ['IN', 'OUT']],
                    [['Core', 'Втулка', 'Vtulka'], 'select_single', true, true, ['0.5"', '1"']],
                    [['Core width', 'Ширина втулки', 'Vtulka kengligi'], 'number', false, false, 'mm', CategoryParameter::CODE_RIBBON_CORE_WIDTH],
                    [['Notch', 'Насечка', 'Kesik'], 'select_single', true, true, [['Notched', 'С насечкой', 'Kesikli'], ['No notch', 'Без насечки', 'Kesiksiz']]],
                    ['TTO', 'select_single', true, true, self::YES_NO],
                    [['Colour', 'Цвет', 'Rangi'], 'select_single', true, true, [
                        ['Black', 'Чёрный', 'Qora'], ['White', 'Белый', 'Oq'], ['Red', 'Красный', 'Qizil'],
                        ['Blue', 'Синий', "Ko'k"], ['Green', 'Зелёный', 'Yashil'], ['Yellow', 'Жёлтый', 'Sariq'],
                        ['Silver', 'Серебряный', 'Kumush'], ['Gold', 'Золотой', 'Oltin'], ['Other', 'Другой', 'Boshqa'],
                    ]],
                    [['Printhead type', 'Тип печатающей головки', 'Bosma kallak turi'], 'select_single', false, true, self::PRINTHEAD],
                    [['Application / industry', 'Применение / отрасль', 'Qo\'llanilishi / soha'], 'select_multiple', false, true, [
                        ['Logistics', 'Логистика', 'Logistika'], ['Retail', 'Розница', 'Chakana savdo'],
                        ['Apparel care labels', 'Ярлыки для одежды', 'Kiyim yorliqlari'], ['Chemical / GHS', 'Химия / GHS', 'Kimyo / GHS'],
                        ['Outdoor', 'Уличное применение', 'Tashqi muhit'], ['Food', 'Пищевая продукция', 'Oziq-ovqat'],
                    ]],
                ],
            ],
            [
                'name' => ['TT labels', 'Термотрансферные этикетки', 'Termotransfer yorliqlar'],
                'icon' => 'label-roll',
                'parameters' => [
                    [['Width', 'Ширина', 'Kengligi'], 'number', true, true, 'mm', CategoryParameter::CODE_LABEL_WIDTH],
                    [['Height', 'Высота', 'Balandligi'], 'number', true, true, 'mm'],
                    [['Labels per roll', 'Этикеток в рулоне', 'Rulondagi yorliqlar'], 'number', true, true, 'pcs'],
                    [['Gap', 'Зазор', 'Oraliq'], 'number', true, false, 'mm'],
                    [['Core', 'Втулка', 'Vtulka'], 'select_single', true, true, ['25 mm', '40 mm', '76 mm']],
                    [['Material', 'Материал', 'Material'], 'select_single', true, true, [
                        ['Coated paper (semi-gloss)', 'Полуглянцевая бумага', 'Yarim yaltiroq qog\'oz'], 'Vellum', 'PP', 'PET',
                        ['Textile / satin', 'Текстиль / сатин', 'Tekstil / atlas'], ['Polyimide', 'Полиимид', 'Poliimid'],
                    ], CategoryParameter::CODE_LABEL_MATERIAL],
                    [['Adhesive', 'Клей', 'Yelim'], 'select_single', true, true, [
                        ['Permanent', 'Постоянный', 'Doimiy'], ['Removable', 'Съёмный', 'Olinadigan'],
                        ['Freezer', 'Морозостойкий', 'Muzlatkich uchun'], ['High-tack', 'Сильный', 'Kuchli'],
                    ]],
                    [['Liner colour', 'Цвет подложки', 'Taglik rangi'], 'select_single', true, false, [
                        ['White', 'Белый', 'Oq'], ['Yellow', 'Жёлтый', 'Sariq'], ['Blue', 'Синий', "Ko'k"],
                        ['Transparent', 'Прозрачный', 'Shaffof'], ['Other', 'Другой', 'Boshqa'],
                    ]],
                    [['Labels across', 'Этикеток в ряду', 'Qatordagi yorliqlar'], 'select_single', false, true, ['1', '2', '3', '4']],
                    [['Roll outer diameter', 'Внешний диаметр рулона', 'Rulon tashqi diametri'], 'number', false, true, 'mm'],
                    [['Print', 'Печать', 'Bosma'], 'select_single', false, true, [['Blank', 'Чистые', "Bo'sh"], ['Pre-printed', 'С печатью', 'Bosilgan']]],
                    [['Recommended ribbon type', 'Рекомендуемый тип риббона', 'Tavsiya etilgan lenta turi'], 'select_multiple', false, true, ['Wax', 'Wax-resin', 'Resin']],
                ],
            ],
            [
                'name' => ['Desktop TT printers', 'Настольные TT принтеры', 'Stol usti TT printerlar'],
                'icon' => 'printer-desktop',
                'parameters' => $printer,
            ],
            [
                'name' => ['Industrial TT printers', 'Промышленные TT принтеры', 'Sanoat TT printerlari'],
                'icon' => 'printer-industrial',
                'parameters' => $printer,
            ],
            [
                'name' => ['Printheads & spare parts', 'Печатающие головки и запчасти', 'Bosma kallaklar va ehtiyot qismlar'],
                'icon' => 'printhead',
                'parameters' => [
                    [['Part type', 'Тип детали', 'Qism turi'], 'select_single', true, true, [
                        ['Printhead', 'Печатающая головка', 'Bosma kallak'], ['Platen roller', 'Прижимной вал', 'Bosuvchi val'],
                        ['Cutter module', 'Модуль отрезчика', 'Kesgich moduli'], ['Peeler module', 'Модуль отделителя', 'Ajratgich moduli'],
                        ['Rewinder module', 'Модуль подмотчика', "O'ragich moduli"], ['Sensor', 'Датчик', 'Sensor'],
                        ['Belt', 'Ремень', 'Tasma'], ['Main board', 'Материнская плата', 'Asosiy plata'],
                        ['Power supply', 'Блок питания', 'Quvvat bloki'], ['Other', 'Другое', 'Boshqa'],
                    ]],
                    [['Fits printer models', 'Подходит к моделям', 'Mos printer modellari'], 'text', true, false, null],
                    // Required only when Part type = Printhead — conditional
                    // rules aren't modelled yet, so optional for now.
                    [['Resolution', 'Разрешение', 'Rezolyutsiya'], 'select_single', false, true, ['203 dpi', '300 dpi', '600 dpi']],
                    [['Original / compatible', 'Оригинал / совместимый', 'Original / mos'], 'select_single', true, true, [
                        ['Original (OEM)', 'Оригинал (OEM)', 'Original (OEM)'], ['Compatible', 'Совместимый', 'Mos'],
                    ]],
                ],
            ],
            [
                'name' => ['Barcode scanners', 'Сканеры штрих-кодов', 'Shtrix-kod skanerlari'],
                'icon' => 'scanner',
                'parameters' => [
                    [['Form factor', 'Тип', 'Turi'], 'select_single', true, true, [
                        ['Handheld', 'Ручной', "Qo'lda"], ['Fixed-mount', 'Стационарный', 'Statsionar'], ['Presentation', 'Настольный', 'Stol usti'],
                    ]],
                    [['Symbologies', 'Типы кодов', 'Kod turlari'], 'select_single', true, true, ['1D', '2D']],
                    [['Interfaces', 'Интерфейсы', 'Interfeyslar'], 'select_multiple', true, true, ['USB', 'RS-232', 'Bluetooth', ['Wireless (radio)', 'Беспроводной (радио)', 'Simsiz (radio)']]],
                ],
            ],
            [
                'name' => ['PDA / mobile computers', 'ТСД / мобильные компьютеры', 'TSD / mobil kompyuterlar'],
                'icon' => 'pda',
                'parameters' => [
                    [['Operating system', 'Операционная система', 'Operatsion tizim'], 'select_single', true, true, ['Android', 'Windows']],
                    [['Screen size', 'Размер экрана', "Ekran o'lchami"], 'number', true, true, 'in'],
                    [['Scanner', 'Сканер', 'Skaner'], 'select_single', true, true, ['1D', '2D', ['None', 'Нет', "Yo'q"]]],
                ],
            ],
            [
                'name' => ['Accessories', 'Аксессуары', 'Aksessuarlar'],
                'icon' => 'accessories',
                'parameters' => [
                    [['Accessory type', 'Тип аксессуара', 'Aksessuar turi'], 'select_single', true, true, [
                        ['External rewinder', 'Внешний подмотчик', "Tashqi o'ragich"], ['Roll holder / unwinder', 'Держатель рулона / размотчик', 'Rulon ushlagich'],
                        ['Label applicator', 'Аппликатор этикеток', 'Yorliq applikatori'], ['Label dispenser', 'Диспенсер этикеток', 'Yorliq dispenseri'],
                        ['Cleaning kit', 'Чистящий набор', 'Tozalash to\'plami'], ['Cable', 'Кабель', 'Kabel'],
                        ['Stand', 'Подставка', 'Tagliq'], ['Other', 'Другое', 'Boshqa'],
                    ]],
                    [['Fits', 'Совместимость', 'Mosligi'], 'select_single', true, true, [
                        ['Universal', 'Универсальный', 'Universal'], ['Specific printer models', 'Определённые модели', 'Muayyan modellar'],
                    ]],
                    // Required when Fits = Specific models (conditional, not modelled yet).
                    [['Fits printer models', 'Подходит к моделям', 'Mos printer modellari'], 'text', false, false, null],
                    [['Max media width', 'Макс. ширина носителя', 'Maks. material kengligi'], 'number', false, true, 'mm'],
                    [['Max roll OD', 'Макс. диаметр рулона', 'Maks. rulon diametri'], 'number', false, true, 'mm'],
                    [['Operation', 'Режим работы', 'Ishlash rejimi'], 'select_single', false, true, [
                        ['Manual', 'Ручной', "Qo'lda"], ['Powered', 'С приводом', 'Elektr yuritmali'],
                        ['Semi-automatic', 'Полуавтоматический', 'Yarim avtomatik'], ['Fully automatic', 'Автоматический', "To'liq avtomatik"],
                    ]],
                ],
            ],
            [
                // Proposed 2026-11-02, awaiting owner confirmation — see categories.md §9.
                // Brand = vendor (Seagull Scientific, Loftware, TEKLYNX, Zebra);
                // model = product line (BarTender, NiceLabel, CODESOFT, ZebraDesigner).
                // One licence is priced as unit `pcs`.
                'name' => ['Label design & printing software', 'ПО для дизайна и печати этикеток', 'Yorliq dizayni va chop etish dasturlari'],
                'icon' => 'software',
                'parameters' => [
                    [['Edition', 'Редакция', 'Nashr'], 'select_single', true, true, [
                        'Starter', 'Designer', 'Professional', 'Automation', 'Enterprise', 'Cloud',
                        ['Free', 'Бесплатная', 'Bepul'], ['Other', 'Другая', 'Boshqa'],
                    ]],
                    [['License type', 'Тип лицензии', 'Litsenziya turi'], 'select_single', true, true, [
                        ['Perpetual', 'Бессрочная', 'Muddatsiz'],
                        ['Subscription (annual)', 'Подписка (год)', 'Obuna (yillik)'],
                        ['Subscription (monthly)', 'Подписка (месяц)', 'Obuna (oylik)'],
                    ]],
                    [['Deployment', 'Развёртывание', 'O\'rnatish'], 'select_multiple', true, true, [
                        ['Windows desktop', 'Windows (локально)', 'Windows (lokal)'],
                        ['Server / network', 'Сервер / сеть', 'Server / tarmoq'],
                        ['Cloud / web', 'Облако / веб', 'Bulut / veb'],
                    ]],
                    [['Printers licensed', 'Принтеров в лицензии', 'Litsenziyadagi printerlar'], 'number', false, true, 'printers'],
                    [['Users / seats', 'Пользователей / мест', 'Foydalanuvchilar / o\'rinlar'], 'number', false, true, 'users'],
                    [['Features', 'Возможности', 'Imkoniyatlar'], 'select_multiple', false, true, [
                        ['Database connection', 'Подключение к базам данных', 'Ma\'lumotlar bazasiga ulanish'],
                        ['ERP / SAP integration', 'Интеграция с ERP / SAP', 'ERP / SAP integratsiyasi'],
                        ['Print automation', 'Автоматизация печати', 'Chop etishni avtomatlashtirish'],
                        ['RFID encoding', 'Кодирование RFID', 'RFID kodlash'],
                        ['Web / mobile printing', 'Печать из веба / мобильных', 'Veb / mobil chop etish'],
                        ['Audit & approval (GMP / FDA)', 'Аудит и согласование (GMP / FDA)', 'Audit va tasdiqlash (GMP / FDA)'],
                    ]],
                    [['Interface language', 'Язык интерфейса', 'Interfeys tili'], 'select_multiple', false, true, [
                        ['Russian', 'Русский', 'Rus'], ['English', 'Английский', 'Ingliz'], ['Uzbek', 'Узбекский', 'O\'zbek'],
                    ]],
                    [['Delivery', 'Поставка', 'Yetkazish'], 'select_single', false, false, [
                        ['Electronic licence key', 'Электронный ключ', 'Elektron kalit'],
                        ['USB dongle', 'USB-ключ', 'USB kalit'],
                        ['Boxed', 'Коробочная версия', 'Qutili versiya'],
                    ]],
                    [['Support / maintenance included', 'Поддержка / обновления включены', 'Qo\'llab-quvvatlash kiritilgan'], 'select_single', false, true, self::YES_NO],
                ],
            ],
        ];
    }

    /**
     * @param  string|array<int, string>  $label  [en, ru, uz] or one locale-invariant string
     * @return array<string, string>
     */
    private function t(string|array $label): array
    {
        [$en, $ru, $uz] = is_array($label) ? $label : [$label, $label, $label];

        return ['uz' => $uz, 'ru' => $ru, 'en' => $en];
    }

    /**
     * @param  array<int, string>  $name
     */
    private function category(array $name, int $sortOrder, bool $requiresSubscription = true, ?string $icon = null): Category
    {
        $name = $this->t($name);
        $slug = array_map(fn (string $value) => Str::slug($value), $name);

        $category = Category::firstOrCreate(
            ['slug->en' => $slug['en']],
            ['name' => $name, 'slug' => $slug, 'is_active' => true, 'requires_subscription' => $requiresSubscription, 'sort_order' => $sortOrder, 'icon' => $icon],
        );

        // Fill the icon on existing rows only when the admin hasn't picked one.
        if ($icon !== null && $category->icon === null) {
            $category->update(['icon' => $icon]);
        }

        return $category;
    }

    /**
     * @param  array<int, mixed>  $definition
     */
    private function parameter(Category $category, array $definition, int $sortOrder): void
    {
        [$name, $type, $required, $filterable, $extra] = $definition;
        $name = $this->t($name);

        $parameter = CategoryParameter::firstOrCreate(
            ['category_id' => $category->id, 'name->en' => $name['en']],
            [
                'code' => $definition[5] ?? null,
                'name' => $name,
                'type' => $type,
                'unit' => $type === 'number' ? $extra : null,
                'is_required' => $required,
                'is_filterable' => $filterable,
                'sort_order' => $sortOrder,
            ],
        );

        if (! in_array($type, ['select_single', 'select_multiple'], true)) {
            return;
        }

        foreach ($extra as $optionOrder => $option) {
            $value = $this->t($option);

            CategoryParameterOption::firstOrCreate(
                ['category_parameter_id' => $parameter->id, 'value->en' => $value['en']],
                ['value' => $value, 'sort_order' => $optionOrder],
            );
        }
    }
}
