<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Real, permanent educational content (history, ribbon types, use cases,
 * technical explainers) for the storefront's Articles section — unlike
 * DemoCatalogSeeder, this is genuine content meant to exist in every
 * environment including production, so it's called from
 * DatabaseSeeder::run() unconditionally. Idempotent via updateOrCreate
 * keyed on the English slug, so re-running `db:seed` doesn't duplicate
 * rows if an article's copy is edited here later.
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::where('email', config('ribbon.super_admin.email'))->value('id');

        foreach ($this->articles() as $index => $data) {
            Article::updateOrCreate(
                ['slug->en' => $data['slug']['en']],
                [
                    // All 10 are evergreen guides, not time-sensitive
                    // announcements — 'article', never 'news'.
                    'type' => 'article',
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'excerpt' => $data['excerpt'],
                    'body' => array_map($this->paragraphsToHtml(...), $data['body']),
                    'published_at' => now()->subDays(20 - ($index * 2)),
                    'created_by' => $createdBy,
                ],
            );
        }

        $this->command?->info('Articles seeded: '.count($this->articles()).' published articles.');
    }

    /**
     * Article bodies below are authored as plain text with blank-line
     * paragraph breaks (predates the Trix rich-text editor) — wrapped in
     * <p> tags here at seed time so they render correctly now that
     * Storefront\Articles\Show outputs body as raw HTML instead of
     * splitting on blank lines itself.
     */
    private function paragraphsToHtml(string $text): string
    {
        return collect(preg_split('/\n\s*\n/', trim($text)))
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter(fn (string $paragraph) => $paragraph !== '')
            ->map(fn (string $paragraph) => '<p>'.e($paragraph).'</p>')
            ->implode('');
    }

    /**
     * @return array<int, array{title: array<string,string>, slug: array<string,string>, excerpt: array<string,string>, body: array<string,string>}>
     */
    private function articles(): array
    {
        return [
            $this->historyArticle(),
            $this->ribbonChemistryArticle(),
            $this->coreSizeArticle(),
            $this->dimensionMatchingArticle(),
            $this->directVsTransferArticle(),
            $this->windingDirectionArticle(),
            $this->scannerTypesArticle(),
            $this->ruggedPdaArticle(),
            $this->labelMaterialArticle(),
            $this->barcodeTypesArticle(),
        ];
    }

    private function historyArticle(): array
    {
        return [
            'slug' => [
                'en' => 'thermal-transfer-printing-history',
                'ru' => 'istoriya-termotransfernoy-pechati',
                'uz' => 'termotransfer-bosib-chiqarish-tarixi',
            ],
            'title' => [
                'en' => 'The History of Thermal Transfer Printing',
                'ru' => 'История термотрансферной печати',
                'uz' => 'Termotransfer bosib chiqarish tarixi',
            ],
            'excerpt' => [
                'en' => 'From industrial coding in the 1970s to the barcode labels on every shelf today — how thermal transfer printing became the backbone of modern auto-ID.',
                'ru' => 'От промышленной маркировки 1970-х до штрих-кодов на полках сегодня — как термотрансферная печать стала основой современной авто-идентификации.',
                'uz' => '1970-yillardagi sanoat markirovkasidan bugungi kunda javonlardagi shtrix-kodlargacha — termotransfer bosib chiqarish qanday qilib zamonaviy avto-ID sohasining asosiga aylandi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Thermal transfer printing traces back to the 1970s and 1980s, when manufacturers needed a reliable way to print variable data — batch numbers, dates, barcodes — directly onto packaging and labels on a production line. Early thermal printing methods used heat-sensitive paper that darkened when heated (direct thermal), but these labels faded in sunlight, heat, or over time, which was unacceptable for anything that needed to stay legible for months or years.

The breakthrough was separating the print mechanism from the label material. A thermal printhead heats tiny elements in a pattern, and instead of reacting with the label itself, that heat melts or transfers a thin layer of pigmented wax or resin from a ribbon onto the label surface. This is thermal transfer printing: the ribbon carries the ink, the label just receives it, and the print becomes far more durable — resistant to scratching, chemicals, heat, and sunlight depending on the ribbon type used.

Through the 1980s and 1990s, as barcoding became standard in retail and logistics (driven by the adoption of UPC and later GS1 standards), thermal transfer printers and ribbons became the default way to produce barcode labels on demand, rather than pre-printing them in bulk. This let warehouses, shipping companies, and retailers print exactly the labels they needed, when they needed them, with the specific barcode, price, or lot number for that item.

Today, thermal transfer technology underpins an enormous range of industries — logistics and shipping labels, retail price tags, pharmaceutical and cold-chain labeling, industrial asset tags, and wiring/cable identification — anywhere a label needs to survive real-world handling. The core technology (printhead + ribbon + label) hasn't fundamentally changed in decades; what has evolved is ribbon chemistry (wax, wax-resin, and full resin formulations for different durability needs), printer speed and resolution, and integration with barcode standards and enterprise software.

Understanding this history explains why choosing the right ribbon still matters today: the ribbon is doing the same job it always has — transferring a durable image onto a label — and matching it to your printer, your label material, and your environment is what determines whether that label survives the trip from the printer to wherever it ends up.
TEXT,
                'ru' => <<<'TEXT'
История термотрансферной печати начинается в 1970-х и 1980-х годах, когда производителям потребовался надёжный способ печатать переменные данные — номера партий, даты, штрих-коды — прямо на упаковке и этикетках в ходе производства. Ранние методы термопечати использовали термочувствительную бумагу, которая темнела при нагреве (прямая термопечать), но такие этикетки выцветали на солнце, от тепла или просто со временем — это было неприемлемо там, где текст должен был оставаться читаемым месяцами или годами.

Прорывом стало разделение механизма печати и материала этикетки. Термоголовка нагревает крошечные элементы по заданному рисунку, и вместо реакции с самой этикеткой это тепло плавит или переносит тонкий слой пигментированного wax или resin с ленты на поверхность этикетки. Это и есть термотрансферная печать: лента несёт краситель, этикетка лишь принимает его, а отпечаток становится значительно более долговечным — устойчивым к царапинам, химикатам, теплу и солнцу, в зависимости от типа используемой ленты.

В 1980-х и 1990-х годах, по мере того как штрихкодирование становилось стандартом в рознице и логистике (благодаря распространению стандартов UPC, а затем GS1), термотрансферные принтеры и ленты стали основным способом печати этикеток со штрих-кодом по требованию, а не предварительной массовой печати. Это позволило складам, транспортным компаниям и розничным сетям печатать именно те этикетки, которые нужны, именно тогда, когда нужно — с конкретным штрих-кодом, ценой или номером партии для данного товара.

Сегодня термотрансферная технология лежит в основе огромного числа отраслей — этикетки для логистики и доставки, ценники в рознице, маркировка фармацевтики и холодовой цепи, промышленные бирки для активов, маркировка проводов и кабелей — везде, где этикетка должна пережить реальные условия эксплуатации. Базовая технология (термоголовка + лента + этикетка) принципиально не менялась десятилетиями; развивалась химия лент (wax, wax-resin и полностью resin составы для разных требований к долговечности), скорость и разрешение печати, а также интеграция со стандартами штрих-кодирования и корпоративным ПО.

Понимание этой истории объясняет, почему правильный выбор ленты важен и сегодня: лента выполняет ту же задачу, что и всегда, — перенос долговечного изображения на этикетку, — и её соответствие принтеру, материалу этикетки и условиям эксплуатации определяет, доживёт ли эта этикетка от принтера до места назначения.
TEXT,
                'uz' => <<<'TEXT'
Termotransfer bosib chiqarish tarixi 1970–1980-yillarga borib taqaladi, o'shanda ishlab chiqaruvchilarga ishlab chiqarish jarayonida partiya raqamlari, sanalar, shtrix-kodlar kabi o'zgaruvchan ma'lumotlarni to'g'ridan-to'g'ri qadoq va yorliqlarga bosib chiqarishning ishonchli usuli kerak bo'lgan. Dastlabki termik bosib chiqarish usullari qizdirilganda qorayadigan issiqlikka sezgir qog'ozdan foydalangan (to'g'ridan-to'g'ri termik bosib chiqarish), ammo bunday yorliqlar quyosh nurida, issiqlikda yoki vaqt o'tishi bilan xiralashib qolardi — bu esa oylab yoki yillab o'qilishi kerak bo'lgan holatlar uchun mos kelmasdi.

Bu boradagi burilish bosib chiqarish mexanizmini yorliq materialidan ajratish edi. Termik bosh belgilangan naqsh bo'yicha mayda elementlarni qizdiradi va bu issiqlik yorliqning o'zi bilan reaksiyaga kirishish o'rniga, lentadagi pigmentlangan vaks yoki rezinning yupqa qatlamini yorliq yuzasiga eritib o'tkazadi. Aynan shu termotransfer bosib chiqarish deb ataladi: lenta bo'yoqni tashiydi, yorliq esa uni faqat qabul qiladi, natijada bosma ancha chidamli bo'ladi — ishlatilgan lenta turiga qarab, chizilishga, kimyoviy moddalarga, issiqlikka va quyosh nuriga chidamli.

1980–1990-yillarda shtrix-kodlash chakana savdo va logistikada standartga aylanishi bilan (UPC, keyinchalik esa GS1 standartlarining qabul qilinishi tufayli), termotransfer printerlar va lentalar shtrix-kodli yorliqlarni oldindan ko'p miqdorda bosib chiqarish o'rniga, talab bo'yicha ishlab chiqarishning asosiy usuliga aylandi. Bu omborlar, yetkazib berish kompaniyalari va chakana savdo tarmoqlariga aynan kerakli yorliqlarni, kerakli vaqtda — har bir mahsulot uchun aniq shtrix-kod, narx yoki partiya raqami bilan — bosib chiqarish imkonini berdi.

Bugungi kunda termotransfer texnologiyasi juda ko'p sohalar uchun asos bo'lib xizmat qiladi — logistika va yetkazib berish yorliqlari, chakana savdo narx yorliqlari, farmatsevtika va sovuq zanjir markirovkasi, sanoat aktivlari uchun belgilar, sim va kabellarni belgilash — yorliq real sharoitlarda ishlatilishiga chidashi kerak bo'lgan har qanday joyda. Asosiy texnologiya (termik bosh + lenta + yorliq) o'nlab yillar davomida tub o'zgarishlarga uchramagan; rivojlangan narsa esa lentalar kimyosi (turli chidamlilik talablari uchun vaks, vaks-rezin va to'liq rezin tarkiblar), bosib chiqarish tezligi va rezolyutsiyasi hamda shtrix-kod standartlari va korporativ dasturiy ta'minot bilan integratsiya bo'ldi.

Ushbu tarixni tushunish nima uchun to'g'ri lentani tanlash bugun ham muhimligini tushuntiradi: lenta hamon o'sha vazifani bajaradi — yorliqqa chidamli tasvirni o'tkazish — va uning printeringizga, yorliq materialiga va ishlatilish sharoitiga mosligi ushbu yorliqning printerdan borar joyigacha "omon qolishini" belgilaydi.
TEXT,
            ],
        ];
    }

    private function ribbonChemistryArticle(): array
    {
        return [
            'slug' => [
                'en' => 'wax-vs-wax-resin-vs-resin-ribbons',
                'ru' => 'wax-wax-resin-resin-lenty-kak-vybrat',
                'uz' => 'vaks-vaks-rezin-rezin-lentalar',
            ],
            'title' => [
                'en' => 'Wax vs. Wax-Resin vs. Resin Ribbons: How to Choose',
                'ru' => 'Wax, wax-resin или resin: как выбрать ленту',
                'uz' => 'Vaks, vaks-rezin yoki rezin: qanday tanlash kerak',
            ],
            'excerpt' => [
                'en' => 'The three ribbon chemistries explained — durability, cost, and which one to buy for your labels.',
                'ru' => 'Три состава ленты, их долговечность, стоимость и какой выбрать для ваших этикеток.',
                'uz' => 'Uchta lenta tarkibi — chidamlilik, narx va yorliqlaringiz uchun qaysi birini tanlash kerakligi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Thermal transfer ribbons come in three basic formulations, and picking the right one is the single biggest factor in whether your labels survive their intended use. All three do the same job — melt a pigmented coating onto a label under heat from the printhead — but they differ in what that coating is made of, and therefore how tough the finished print is.

Wax ribbons are the most economical option and print well on uncoated paper labels. The image resists light handling and moisture but scratches and smears relatively easily, and it has poor resistance to chemicals, heat, and direct sunlight. Wax is the right choice for short-term labels: shipping labels, retail price tags, and other labels that will be read once and discarded within days or weeks.

Wax-resin ribbons add resin to the wax formulation, producing a print that resists scratching, smudging, and mild chemical exposure much better, while still working on standard paper labels in most cases. This is the most common all-purpose choice for logistics, warehouse, and general industrial labeling that needs to survive handling and a longer shelf life than a wax label would.

Resin ribbons are pure resin, designed to print on synthetic label stock (polypropylene, polyester, vinyl) rather than paper. They produce the most durable image — resistant to solvents, extreme temperatures, abrasion, and prolonged outdoor exposure. Resin is the standard for asset tags, wire and cable identification, and any label that needs to survive years outdoors or in harsh industrial or cleanroom environments.

As a rule of thumb: match wax to paper labels you'll discard soon, wax-resin to paper labels that need to last through a supply chain, and resin to synthetic labels that need to last for years. The ribbon and label material need to be compatible with each other and with your specific printer — when in doubt, check the seller's listed compatibility for both before ordering.
TEXT,
                'ru' => <<<'TEXT'
Термотрансферные ленты выпускаются в трёх основных составах, и правильный выбор — главный фактор того, переживёт ли этикетка свой срок службы. Все три типа выполняют одну и ту же задачу — под воздействием тепла термоголовки переносят пигментированное покрытие на этикетку, — но отличаются составом этого покрытия, а значит и прочностью готового отпечатка.

Wax-ленты — самый экономичный вариант, хорошо подходящий для немелованных бумажных этикеток. Изображение устойчиво к лёгкому обращению и влаге, но довольно легко царапается и размазывается, плохо переносит химикаты, тепло и прямой солнечный свет. Wax — правильный выбор для краткосрочных этикеток: доставочных ярлыков, розничных ценников и других этикеток, которые прочитают один раз и выбросят в течение дней или недель.

Wax-resin ленты добавляют resin к составу wax, благодаря чему отпечаток намного лучше переносит царапины, размазывание и лёгкое химическое воздействие, при этом в большинстве случаев по-прежнему работая со стандартными бумажными этикетками. Это самый распространённый универсальный выбор для логистики, складов и общей промышленной маркировки, которой нужно пережить обращение и более долгий срок хранения, чем у wax-этикетки.

Resin-ленты полностью состоят из resin и предназначены для печати на синтетических материалах этикеток (полипропилен, полиэстер, винил), а не на бумаге. Они дают самое долговечное изображение — устойчивое к растворителям, экстремальным температурам, истиранию и длительному пребыванию на улице. Resin — стандарт для бирок активов, маркировки проводов и кабелей и любых этикеток, которые должны прослужить годы на улице или в жёстких промышленных условиях либо чистых помещениях.

Простое правило: wax — для бумажных этикеток, которые скоро выбросят; wax-resin — для бумажных этикеток, которым нужно пережить путь по цепочке поставок; resin — для синтетических этикеток на годы вперёд. Лента и материал этикетки должны быть совместимы друг с другом и с конкретным принтером — если сомневаетесь, перед заказом уточните у продавца совместимость обоих.
TEXT,
                'uz' => <<<'TEXT'
Termotransfer lentalari uchta asosiy tarkibda ishlab chiqariladi va to'g'ri tanlov — yorliqning o'z vazifasini bajarib bo'lishida eng muhim omildir. Uchala tur ham bir xil vazifani bajaradi — termik bosh issiqligi ostida pigmentlangan qoplamani yorliqqa o'tkazadi, — ammo bu qoplamaning tarkibi, demak, tayyor bosmaning chidamliligi bilan farqlanadi.

Vaks lentalar eng arzon variant bo'lib, qoplanmagan qog'oz yorliqlarda yaxshi ishlaydi. Tasvir yengil muomala va namlikka chidamli, ammo nisbatan oson chiziladi va yoyiladi, kimyoviy moddalarga, issiqlikka va to'g'ridan-to'g'ri quyosh nuriga chidamliligi past. Vaks qisqa muddatli yorliqlar uchun to'g'ri tanlov: yetkazib berish yorliqlari, chakana savdo narx yorliqlari va bir necha kun yoki hafta ichida o'qilib, tashlab yuboriladigan boshqa yorliqlar.

Vaks-rezin lentalar vaks tarkibiga rezin qo'shadi, natijada bosma chizilish, yoyilish va yengil kimyoviy ta'sirga ancha yaxshi chidaydi, ko'pincha standart qog'oz yorliqlarda ham ishlayveradi. Bu logistika, ombor va yetkazib berish zanjirida vaks yorliqqa qaraganda uzoqroq saqlanishi kerak bo'lgan umumiy sanoat markirovkasi uchun eng ko'p qo'llaniladigan universal tanlovdir.

Rezin lentalar to'liq rezindan iborat bo'lib, qog'oz emas, sintetik yorliq materiallari (polipropilen, poliester, vinil) uchun mo'ljallangan. Ular eng chidamli tasvirni beradi — erituvchilarga, ekstremal haroratlarga, ishqalanishga va uzoq vaqt ochiq havoda turishga chidamli. Rezin aktivlar uchun belgilar, sim va kabellarni belgilash hamda yillar davomida ochiq havoda yoki og'ir sanoat sharoitida ishlashi kerak bo'lgan har qanday yorliq uchun standartdir.

Oddiy qoida: vaksni tez orada tashlab yuboriladigan qog'oz yorliqlar uchun, vaks-rezinni yetkazib berish zanjirida chidashi kerak bo'lgan qog'oz yorliqlar uchun, rezinni esa yillar davomida xizmat qilishi kerak bo'lgan sintetik yorliqlar uchun tanlang. Lenta va yorliq materiali bir-biriga hamda aniq printeringizga mos bo'lishi kerak — shubha bo'lsa, buyurtma berishdan oldin sotuvchidan ikkalasining mosligini so'rang.
TEXT,
            ],
        ];
    }

    private function coreSizeArticle(): array
    {
        return [
            'slug' => [
                'en' => 'why-1-inch-1-5-inch-cores',
                'ru' => 'pochemu-1-i-1-5-dyuym-serdechnik',
                'uz' => 'nima-uchun-1-va-1-5-dyuym-yadro',
            ],
            'title' => [
                'en' => 'Why Ribbon Cores Come in 1" and 1.5" Sizes',
                'ru' => 'Почему сердечники лент бывают 1 и 1,5 дюйма',
                'uz' => 'Nima uchun lenta yadrolari 1 va 1,5 dyuym o\'lchamda bo\'ladi',
            ],
            'excerpt' => [
                'en' => 'The two standard ribbon core diameters exist for a real mechanical reason — here\'s what determines which one your printer needs.',
                'ru' => 'Два стандартных диаметра сердечника ленты существуют по реальной механической причине — вот что определяет, какой нужен вашему принтеру.',
                'uz' => 'Lenta yadrosining ikkita standart diametri haqiqiy mexanik sabab bilan mavjud — printeringizga qaysi biri kerakligini nima belgilaydi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
If you've compared ribbons from different sellers, you've probably noticed the core — the cardboard or plastic tube the ribbon is wound around — comes in two common inner diameters: 1 inch (25.4mm) and 1.5 inch (38.1mm). This isn't a branding choice; it's a mechanical fit requirement, and using the wrong one means the ribbon simply won't mount on your printer's ribbon supply/take-up spindles.

Desktop and many mid-range industrial thermal transfer printers are built around a 1-inch spindle — this covers the majority of label printers used in retail back-offices, small warehouses, and general-purpose labeling. Larger industrial printers, especially high-throughput models designed for long print runs, more commonly use a 1.5-inch spindle. The larger core lets the printer wind a longer ribbon roll onto the same physical diameter, which means fewer ribbon changes during long production runs — a real efficiency gain in high-volume environments.

The core size is entirely independent of the ribbon's width or length: a 1-inch-core ribbon and a 1.5-inch-core ribbon can both be 110mm wide and 300m long — the core diameter only determines which printer it physically fits, not what it prints. Some printers ship with an adapter that lets a 1-inch-core ribbon run on a printer built for 1.5-inch cores, but this isn't universal, and it's not something to assume without checking your printer's manual or the seller's compatibility notes.

When ordering, always check your printer model's spindle size first, then filter for ribbons with that exact core diameter — the width and length can be adjusted for your label size and desired print run, but the core size has no flexibility at all.
TEXT,
                'ru' => <<<'TEXT'
Если вы сравнивали ленты разных продавцов, вы наверняка замечали, что сердечник — картонная или пластиковая втулка, на которую намотана лента, — бывает двух распространённых внутренних диаметров: 1 дюйм (25,4 мм) и 1,5 дюйма (38,1 мм). Это не вопрос бренда, а требование механической совместимости: использование неподходящего сердечника означает, что лента просто не встанет на подающий или приёмный шпиндель вашего принтера.

Настольные и многие промышленные принтеры среднего класса построены под шпиндель 1 дюйм — это охватывает большинство принтеров этикеток, используемых в розничных офисах, небольших складах и для общей маркировки. Более крупные промышленные принтеры, особенно высокопроизводительные модели для длинных тиражей печати, чаще используют шпиндель 1,5 дюйма. Больший сердечник позволяет намотать более длинную ленту при том же физическом диаметре рулона, а значит — реже менять ленту при длинных производственных прогонах, что даёт реальную экономию времени при больших объёмах.

Диаметр сердечника никак не связан с шириной или длиной ленты: лента с сердечником 1 дюйм и лента с сердечником 1,5 дюйма могут быть одинаково шириной 110 мм и длиной 300 м — диаметр сердечника определяет только то, на какой принтер лента физически встанет, а не то, что она печатает. Некоторые принтеры поставляются с переходником, позволяющим использовать ленту с сердечником 1 дюйм на принтере, рассчитанном на 1,5 дюйма, но это не универсально, и полагаться на это без проверки инструкции принтера или примечаний продавца о совместимости не стоит.

При заказе всегда сначала проверяйте диаметр шпинделя вашей модели принтера, а затем фильтруйте ленты именно по этому диаметру сердечника — ширину и длину можно подобрать под размер этикетки и нужный тираж печати, а вот диаметр сердечника не имеет никакой гибкости.
TEXT,
                'uz' => <<<'TEXT'
Turli sotuvchilarning lentalarini solishtirgan bo'lsangiz, yadro — lenta o'ralgan karton yoki plastik naycha — ikkita keng tarqalgan ichki diametrda bo'lishini payqagansiz: 1 dyuym (25,4 mm) va 1,5 dyuym (38,1 mm). Bu brend tanlovi emas — mexanik moslik talabi, va noto'g'ri o'lchamni ishlatish lentaning printeringizning yetkazib berish yoki qabul qilish shpindeliga oddiygina o'rnatilmasligini anglatadi.

Stol usti va ko'plab o'rta darajadagi sanoat termotransfer printerlari 1 dyuymli shpindel atrofida qurilgan — bu chakana savdo ofislari, kichik omborlar va umumiy markirovka uchun ishlatiladigan yorliq printerlarining ko'pchiligini qamrab oladi. Kattaroq sanoat printerlari, ayniqsa uzoq bosib chiqarish uchun mo'ljallangan yuqori unumdorlikdagi modellar, ko'pincha 1,5 dyuymli shpindeldan foydalanadi. Kattaroq yadro bir xil jismoniy diametrda uzunroq lenta o'rashga imkon beradi, demak — uzoq ishlab chiqarish jarayonida lentani kamroq almashtirish kerak bo'ladi, bu esa katta hajmdagi ishlarda haqiqiy samaradorlik beradi.

Yadro o'lchami lentaning kengligi yoki uzunligidan mutlaqo mustaqil: 1 dyuymli yadroli lenta ham, 1,5 dyuymli yadroli lenta ham 110 mm kenglikda va 300 m uzunlikda bo'lishi mumkin — yadro diametri faqat lenta qaysi printerga jismoniy jihatdan mos kelishini belgilaydi, nima bosib chiqarishini emas. Ba'zi printerlar 1 dyuymli yadroli lentani 1,5 dyuym uchun mo'ljallangan printerda ishlatishga imkon beruvchi adapter bilan birga keladi, ammo bu universal emas va printer qo'llanmasi yoki sotuvchining moslik bo'yicha izohlarisiz buni taxmin qilmaslik kerak.

Buyurtma berishda avval printer modelingizning shpindel o'lchamini tekshiring, so'ngra aynan shu yadro diametriga ega lentalarni filtrlang — kenglik va uzunlikni yorliq o'lchamingiz va kerakli bosib chiqarish hajmiga moslab o'zgartirish mumkin, ammo yadro o'lchamida hech qanday moslashuvchanlik yo'q.
TEXT,
            ],
        ];
    }

    private function dimensionMatchingArticle(): array
    {
        return [
            'slug' => [
                'en' => 'matching-ribbon-width-length-core',
                'ru' => 'kak-podobrat-shirinu-dlinu-serdechnik-lenty',
                'uz' => 'lenta-kengligi-uzunligi-yadrosini-moslashtirish',
            ],
            'title' => [
                'en' => 'Matching Ribbon Width, Length, and Core to Your Printer',
                'ru' => 'Как подобрать ширину, длину и сердечник ленты под принтер',
                'uz' => 'Lenta kengligi, uzunligi va yadrosini printeringizga moslashtirish',
            ],
            'excerpt' => [
                'en' => 'The three ribbon dimensions that must fit your printer and label — and what goes wrong when they don\'t.',
                'ru' => 'Три физических параметра ленты, которые должны подходить принтеру и этикетке — и что происходит, если они не совпадают.',
                'uz' => 'Printer va yorliqqa mos kelishi kerak bo\'lgan uchta fizik o\'lcham — va ular mos kelmaganda nima noto\'g\'ri bo\'ladi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Beyond ribbon chemistry, three physical dimensions determine whether a ribbon will actually work in your printer: width, length, and core diameter. Getting any one of them wrong causes a real problem, not just a minor inconvenience.

Ribbon width should be equal to or very slightly wider than your label's width. A ribbon that's too narrow leaves parts of the label unprinted at the edges. A ribbon significantly wider than the label wastes ribbon (and money) with every print, since the unused width is consumed at the same rate as the printed portion — ribbon is consumed by length printed, not by how much of the width is actually used.

Ribbon length determines how many labels you can print before changing the ribbon, and needs to be compatible with your printer's maximum ribbon roll diameter — a longer ribbon on a thin core takes up more outer diameter, and if it doesn't fit inside the printer's ribbon compartment, it simply won't close or spin freely. Sellers list both width and length for every ribbon; check both against your printer's specifications, not just your label size.

Core diameter, as covered in a separate article on 1-inch vs. 1.5-inch cores, is a hard mechanical requirement with no flexibility — it must match your printer's spindle exactly.

When ordering from a new seller, the safest approach is to check the exact width, length, and core diameter of a ribbon you already know works in your printer, and match all three when comparing listings — not just the price.
TEXT,
                'ru' => <<<'TEXT'
Помимо состава ленты, три физических параметра определяют, будет ли лента реально работать в вашем принтере: ширина, длина и диаметр сердечника. Ошибка в любом из них — это реальная проблема, а не мелкое неудобство.

Ширина ленты должна быть равна или чуть больше ширины этикетки. Слишком узкая лента оставляет непропечатанные участки по краям этикетки. Лента, значительно шире этикетки, впустую расходуется (и деньги вместе с ней) при каждой печати, поскольку неиспользуемая ширина расходуется с той же скоростью, что и рабочая часть — лента расходуется по длине печати, а не по тому, насколько используется её ширина.

Длина ленты определяет, сколько этикеток можно напечатать до её замены, и должна быть совместима с максимальным диаметром рулона ленты вашего принтера — более длинная лента на тонком сердечнике занимает больший внешний диаметр, и если она не помещается в отсек для ленты принтера, крышка просто не закроется или лента не будет свободно вращаться. Продавцы указывают и ширину, и длину для каждой ленты; сверяйте оба параметра со спецификацией принтера, а не только с размером этикетки.

Диаметр сердечника, как описано в отдельной статье о сердечниках 1 и 1,5 дюйма, — жёсткое механическое требование без какой-либо гибкости: он должен точно соответствовать шпинделю вашего принтера.

При заказе у нового продавца самый надёжный подход — сверить точную ширину, длину и диаметр сердечника ленты, которая уже точно работает в вашем принтере, и сравнивать все три параметра при выборе среди предложений — а не только цену.
TEXT,
                'uz' => <<<'TEXT'
Lenta tarkibidan tashqari, uchta fizik o'lcham lentaning printeringizda haqiqatan ishlashini belgilaydi: kengligi, uzunligi va yadro diametri. Ulardan birortasida xato qilish — kichik noqulaylik emas, haqiqiy muammo.

Lenta kengligi yorliq kengligiga teng yoki undan sal kattaroq bo'lishi kerak. Juda tor lenta yorliq chetlarida bosilmagan joylarni qoldiradi. Yorliqdan sezilarli darajada kengroq lenta esa har bir bosib chiqarishda behuda sarflanadi (demak, pul ham) — chunki ishlatilmagan kenglik ham bosilgan qism bilan bir xil tezlikda sarflanadi; lenta kengligining qancha qismi ishlatilishidan qat'i nazar, bosib chiqarilgan uzunlik bo'yicha sarflanadi.

Lenta uzunligi lentani almashtirishdan oldin necha dona yorliq bosib chiqarish mumkinligini belgilaydi va printeringizning maksimal lenta rulon diametriga mos bo'lishi kerak — ingichka yadrodagi uzunroq lenta kattaroq tashqi diametrni egallaydi, va agar u printerning lenta bo'limiga sig'masa, qopqoq oddiygina yopilmaydi yoki lenta erkin aylanmaydi. Sotuvchilar har bir lenta uchun ham kengligini, ham uzunligini ko'rsatadi; ikkalasini ham faqat yorliq o'lchamiga emas, printer spetsifikatsiyasiga solishtiring.

Yadro diametri, 1 va 1,5 dyuymli yadrolar haqidagi alohida maqolada aytilganidek, hech qanday moslashuvchanlikka ega bo'lmagan qat'iy mexanik talabdir — u printeringiz shpindeliga aniq mos kelishi kerak.

Yangi sotuvchidan buyurtma berishda eng ishonchli yo'l — printeringizda allaqachon ishlayotganini bilgan lentaning aniq kengligi, uzunligi va yadro diametrini tekshirish va takliflarni solishtirishda faqat narxni emas, uchalasini ham solishtirishdir.
TEXT,
            ],
        ];
    }

    private function directVsTransferArticle(): array
    {
        return [
            'slug' => [
                'en' => 'direct-thermal-vs-thermal-transfer',
                'ru' => 'pryamaya-termopechat-i-termotransfernaya-pechat',
                'uz' => 'togridan-togri-termik-va-termotransfer',
            ],
            'title' => [
                'en' => 'Direct Thermal vs. Thermal Transfer Printing: What\'s the Difference',
                'ru' => 'Прямая термопечать и термотрансферная печать: в чём разница',
                'uz' => 'To\'g\'ridan-to\'g\'ri termik va termotransfer bosib chiqarish: farqi nimada',
            ],
            'excerpt' => [
                'en' => 'Two different label technologies, often confused — here\'s when each one is the right choice.',
                'ru' => 'Две разные технологии печати этикеток, которые часто путают — когда выбирать каждую из них.',
                'uz' => 'Ko\'pincha adashtiriladigan ikkita xil yorliq texnologiyasi — qachon qaysi birini tanlash kerak.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Direct thermal and thermal transfer printers can look nearly identical, and both produce barcode labels, which is why buyers sometimes order the wrong ribbon for a printer that doesn't use one at all. The difference is fundamental: direct thermal printers have no ribbon — the label paper itself is chemically treated to darken when heated by the printhead. Thermal transfer printers use a ribbon, which carries the ink that gets transferred onto a separate, untreated label.

Direct thermal is simpler and cheaper per label — no ribbon to buy or replace — but the printed image fades over time, especially with heat or sunlight exposure, since the same chemical reaction that created the image can continue reacting to further heat. This makes direct thermal a good fit for short-lived labels: shipping labels that are scanned once and discarded, receipts, and other documents with a useful life measured in days or weeks.

Thermal transfer, using a ribbon matched to the label material, produces a print that can last months or years depending on the ribbon chemistry, and resists fading from heat and light far better. This makes it the right choice for labels that need to remain legible for a long time: product labels on a shelf, asset tags, compliance labels, or anything stored for an extended period before it's read.

If you're buying a new printer, check whether it's direct-thermal-only, thermal-transfer-only, or dual-capable (many industrial models support both, with a ribbon supply mechanism you simply don't load for direct thermal printing). If you already have a printer and aren't sure which type it is, check whether it has a visible ribbon spool — if it doesn't, it's direct thermal, and ordering a ribbon for it would be a wasted purchase.
TEXT,
                'ru' => <<<'TEXT'
Принтеры прямой термопечати и термотрансферные принтеры могут выглядеть почти одинаково, и оба печатают этикетки со штрих-кодом, поэтому покупатели иногда заказывают ленту для принтера, который её вообще не использует. Разница принципиальна: у принтеров прямой термопечати нет ленты — сама бумага этикетки химически обработана так, чтобы темнеть при нагреве термоголовкой. Термотрансферные принтеры используют ленту, которая несёт краситель, переносимый на отдельную, необработанную этикетку.

Прямая термопечать проще и дешевле в расчёте на этикетку — не нужно покупать и менять ленту, — но отпечаток со временем выцветает, особенно от тепла или солнечного света, поскольку та же химическая реакция, что создала изображение, может продолжать реагировать на дальнейшее тепло. Это делает прямую термопечать удачным выбором для недолговечных этикеток: доставочных ярлыков, которые сканируют один раз и выбрасывают, чеков и других документов со сроком службы в дни или недели.

Термотрансферная печать с лентой, подобранной под материал этикетки, даёт отпечаток, который может прослужить месяцы или годы в зависимости от состава ленты, и значительно лучше сопротивляется выцветанию от тепла и света. Это делает её правильным выбором для этикеток, которые должны оставаться читаемыми долгое время: товарных этикеток на полке, бирок активов, этикеток соответствия требованиям или всего, что хранится длительное время до прочтения.

Если вы покупаете новый принтер, уточните, поддерживает ли он только прямую термопечать, только термотрансферную, или оба режима (многие промышленные модели поддерживают оба, с механизмом подачи ленты, который просто не заправляют для прямой термопечати). Если у вас уже есть принтер и вы не уверены, какого он типа, проверьте, есть ли у него видимая катушка для ленты — если её нет, это прямая термопечать, и заказ ленты для него будет напрасной покупкой.
TEXT,
                'uz' => <<<'TEXT'
To'g'ridan-to'g'ri termik va termotransfer printerlar deyarli bir xil ko'rinishi mumkin va ikkalasi ham shtrix-kodli yorliqlarni bosib chiqaradi, shuning uchun xaridorlar ba'zan lentani umuman ishlatmaydigan printer uchun lenta buyurtma qilib qo'yishadi. Farq tubdan: to'g'ridan-to'g'ri termik printerlarda lenta yo'q — yorliq qog'ozining o'zi termik bosh qizdirganda qorayadigan qilib kimyoviy ishlangan. Termotransfer printerlar esa lentadan foydalanadi, u alohida, ishlanmagan yorliqqa o'tkaziladigan bo'yoqni tashiydi.

To'g'ridan-to'g'ri termik bosib chiqarish har bir yorliq uchun sodda va arzonroq — lenta sotib olish va almashtirish shart emas, — ammo bosma vaqt o'tishi bilan, ayniqsa issiqlik yoki quyosh nuriga chidashi bilan, xiralashadi, chunki tasvirni yaratgan xuddi shu kimyoviy reaksiya keyingi issiqlikka ham javob berishda davom etishi mumkin. Bu to'g'ridan-to'g'ri termik bosib chiqarishni qisqa muddatli yorliqlar uchun yaxshi tanlov qiladi: bir marta skanerlanib tashlab yuboriladigan yetkazib berish yorliqlari, cheklar va kunlar yoki haftalar davomida foydali bo'ladigan boshqa hujjatlar.

Yorliq materialiga mos lenta bilan termotransfer bosib chiqarish, lenta tarkibiga qarab, oylar yoki yillar davom etadigan bosmani beradi va issiqlik hamda yorug'likdan xiralashishga ancha yaxshi chidaydi. Bu uni uzoq vaqt o'qilishi kerak bo'lgan yorliqlar uchun to'g'ri tanlov qiladi: javondagi mahsulot yorliqlari, aktiv belgilari, muvofiqlik yorliqlari yoki o'qilishidan oldin uzoq vaqt saqlanadigan har qanday narsa.

Agar yangi printer sotib olayotgan bo'lsangiz, u faqat to'g'ridan-to'g'ri termik, faqat termotransfer yoki ikkalasini ham qo'llab-quvvatlaydimi (ko'plab sanoat modellari ikkalasini ham qo'llab-quvvatlaydi, lenta ta'minoti mexanizmi bilan, uni to'g'ridan-to'g'ri termik bosib chiqarish uchun shunchaki yuklamaysiz), tekshiring. Agar printeringiz allaqachon bo'lsa va uning qaysi turga tegishli ekanligiga ishonchingiz komil bo'lmasa, unda ko'rinib turgan lenta g'altagi bor-yo'qligini tekshiring — agar yo'q bo'lsa, bu to'g'ridan-to'g'ri termik, va unga lenta buyurtma qilish behuda xarajat bo'ladi.
TEXT,
            ],
        ];
    }

    private function windingDirectionArticle(): array
    {
        return [
            'slug' => [
                'en' => 'inside-wound-vs-outside-wound-ribbons',
                'ru' => 'namotka-lenty-vnutr-i-naruzhu',
                'uz' => 'lentaning-ichkariga-va-tashqariga-oralishi',
            ],
            'title' => [
                'en' => 'Inside-Wound vs. Outside-Wound Ribbons: Why It Matters',
                'ru' => 'Намотка ленты внутрь и наружу: почему это важно',
                'uz' => 'Lentaning ichkariga va tashqariga o\'ralishi: nima uchun bu muhim',
            ],
            'excerpt' => [
                'en' => 'A ribbon that looks identical to the one you always order can still be wrong — because of which side the coating faces.',
                'ru' => 'Лента, внешне идентичная той, что вы всегда заказываете, всё же может не подойти — из-за того, в какую сторону обращено покрытие.',
                'uz' => 'Doim buyurtma qiladigan lentangizga tashqi ko\'rinishi bir xil lenta ham mos kelmasligi mumkin — qoplama qaysi tomonga qaraganiga bog\'liq.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Beyond width, length, and core diameter, one more compatibility detail catches buyers out: ribbon winding direction. A ribbon's ink coating can face either inward (coating-in, or "inside wound") or outward (coating-out, or "outside wound") relative to the roll. Both types are common, and printers are built for one or the other — not both.

The winding direction determines how the ribbon must be threaded through the printer so the coated side actually contacts the label at the point of printing. Load a ribbon with the wrong winding direction into a printer built for the other, and either nothing will print, or the printhead will run directly against the ribbon's uncoated backing, which can damage the printhead over time — an expensive mistake for the sake of a cheap ribbon.

There's no reliable way to guess a printer's required winding direction from its size, brand, or price point — it's a specification set by the manufacturer for that model, and it doesn't change. The printer's manual will state it explicitly, usually as "coating in" or "coating out," and most printer manufacturers stick to one convention across their whole product line, but exceptions exist.

When ordering a ribbon for a printer you haven't bought ribbons for before, check the winding direction alongside width, length, and core size — sellers list this specification for exactly this reason, and it's worth confirming even when everything else about a listing looks like an exact match to what you've used before.
TEXT,
                'ru' => <<<'TEXT'
Помимо ширины, длины и диаметра сердечника, есть ещё одна деталь совместимости, из-за которой покупатели ошибаются: направление намотки ленты. Красящее покрытие ленты может быть обращено либо внутрь рулона («намотка внутрь»), либо наружу («намотка наружу»). Оба варианта распространены, и принтеры рассчитаны на один из них — не на оба сразу.

Направление намотки определяет, как ленту нужно заправить в принтер, чтобы покрытая сторона действительно соприкасалась с этикеткой в точке печати. Если заправить ленту с неправильным направлением намотки в принтер, рассчитанный на другое, либо ничего не напечатается, либо термоголовка будет тереться прямо о непокрытую подложку ленты, что со временем может повредить термоголовку — дорогая ошибка ради экономии на ленте.

Надёжного способа угадать требуемое направление намотки принтера по его размеру, бренду или цене не существует — это спецификация, заданная производителем для конкретной модели, и она не меняется. В инструкции к принтеру это указано явно, обычно как «намотка внутрь» или «намотка наружу», и большинство производителей придерживаются одной конвенции для всей линейки продуктов, но исключения бывают.

Заказывая ленту для принтера, для которого вы раньше ленты не покупали, проверяйте направление намотки наряду с шириной, длиной и диаметром сердечника — продавцы указывают эту характеристику именно по этой причине, и её стоит уточнить, даже если всё остальное в объявлении выглядит точным совпадением с тем, что вы использовали раньше.
TEXT,
                'uz' => <<<'TEXT'
Kenglik, uzunlik va yadro diametridan tashqari, xaridorlarni adashtiradigan yana bir moslik detali bor: lentaning o'ralish yo'nalishi. Lentaning bo'yoq qoplamasi rulonga nisbatan ichkariga ("ichkariga o'ralgan") yoki tashqariga ("tashqariga o'ralgan") qaragan bo'lishi mumkin. Ikkala turi ham keng tarqalgan, va printerlar ulardan faqat bittasiga mo'ljallangan — ikkalasiga emas.

O'ralish yo'nalishi lentani printerga qanday o'tkazish kerakligini belgilaydi, shunda qoplangan tomon bosib chiqarish nuqtasida yorliq bilan haqiqatan aloqa qiladi. Noto'g'ri o'ralish yo'nalishiga ega lentani boshqa yo'nalishga mo'ljallangan printerga yuklasangiz, yoki hech narsa bosilmaydi, yoki termik bosh to'g'ridan-to'g'ri lentaning qoplanmagan orqa tomoniga ishqalanadi, bu esa vaqt o'tishi bilan termik boshni shikastlashi mumkin — arzon lenta tufayli qimmatga tushadigan xato.

Printerning talab qiladigan o'ralish yo'nalishini uning o'lchami, brendi yoki narxidan taxmin qilishning ishonchli usuli yo'q — bu ishlab chiqaruvchi tomonidan o'sha model uchun belgilangan spetsifikatsiya bo'lib, u o'zgarmaydi. Printer qo'llanmasida bu aniq ko'rsatiladi, odatda "qoplama ichkarida" yoki "qoplama tashqarida" tarzida, va ko'pchilik ishlab chiqaruvchilar butun mahsulot liniyasi bo'ylab bitta konventsiyaga amal qiladi, ammo istisnolar ham bor.

Ilgari lenta sotib olmagan printeringiz uchun lenta buyurtma qilayotganda, kenglik, uzunlik va yadro o'lchami bilan bir qatorda o'ralish yo'nalishini ham tekshiring — sotuvchilar aynan shu sababdan bu xususiyatni ko'rsatishadi, va e'londagi boshqa hamma narsa ilgari ishlatganingiz bilan aniq mos kelayotgandek tuyulsa ham, buni tasdiqlash kerak.
TEXT,
            ],
        ];
    }

    private function scannerTypesArticle(): array
    {
        return [
            'slug' => [
                'en' => 'barcode-scanner-types-explained',
                'ru' => 'tipy-skanerov-shtrih-koda',
                'uz' => 'shtrix-kod-skanerlari-turlari',
            ],
            'title' => [
                'en' => 'Barcode Scanner Types Explained: Laser, Linear Imager, 2D Imager',
                'ru' => 'Типы сканеров штрих-кода: лазерный, линейный имиджер, 2D-имиджер',
                'uz' => 'Shtrix-kod skanerlari turlari: lazerli, chiziqli imijer, 2D imijer',
            ],
            'excerpt' => [
                'en' => 'Three scanning technologies, and which one actually fits your warehouse, store, or clinic.',
                'ru' => 'Три технологии сканирования — какая реально подходит вашему складу, магазину или клинике.',
                'uz' => 'Uchta skanerlash texnologiyasi — omboringiz, do\'koningiz yoki klinikangizga aslida qaysi biri mos keladi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Not all barcode scanners read the same codes the same way, and picking the wrong technology means either slow scanning, frequent misreads, or an inability to read the codes you actually need. The three main types found in day-to-day use are laser scanners, linear (1D) imagers, and 2D imagers.

Laser scanners use a moving laser beam to sweep across a barcode and measure the reflected light, decoding standard 1D (linear) barcodes like UPC and Code 128. They're fast, reliable at a range of distances, and inexpensive, but they can only read 1D barcodes — they cannot read 2D codes like QR codes or DataMatrix, and they generally can't read a barcode directly off a phone screen.

Linear (1D) imagers use a small camera sensor instead of a laser to capture an image of the barcode and decode it digitally. They read the same 1D barcode types as laser scanners, often more tolerantly (less precise aiming needed), and since there's no moving laser mechanism, they tend to be more durable in drop-heavy environments. Like laser scanners, though, they're still limited to 1D codes.

2D imagers capture a full image and can decode both 1D barcodes and 2D codes — QR codes, DataMatrix, PDF417 — as well as barcodes displayed on a screen, damaged or poorly printed codes that a laser might reject, and in many cases barcodes at odd angles that a linear scanner would need re-aimed for. This flexibility comes at a somewhat higher price than 1D-only scanners.

For a general retail counter or warehouse handling only standard product barcodes, a 1D laser or linear imager is often enough and costs less. Any environment that needs to scan QR codes, mobile boarding passes or tickets, ID documents, or damaged/dirty labels needs a 2D imager — increasingly the default choice as 2D codes become more common across industries.
TEXT,
                'ru' => <<<'TEXT'
Не все сканеры штрих-кода считывают коды одинаково, и выбор неподходящей технологии означает либо медленное сканирование, либо частые ошибки считывания, либо неспособность прочитать нужные вам коды. Три основных типа, встречающихся в повседневном использовании, — это лазерные сканеры, линейные (1D) имиджеры и 2D-имиджеры.

Лазерные сканеры используют движущийся лазерный луч, который проходит по штрих-коду, измеряя отражённый свет, и декодируют стандартные 1D (линейные) штрих-коды, такие как UPC и Code 128. Они быстрые, надёжные на разных расстояниях и недорогие, но умеют читать только 1D-коды — они не могут прочитать 2D-коды вроде QR-кодов или DataMatrix и, как правило, не считывают штрих-код прямо с экрана телефона.

Линейные (1D) имиджеры вместо лазера используют небольшой сенсор камеры, чтобы захватить изображение штрих-кода и декодировать его цифровым способом. Они читают те же типы 1D-кодов, что и лазерные сканеры, часто более терпимо к неточному прицеливанию, а из-за отсутствия движущегося лазерного механизма обычно более долговечны в условиях частых падений. Однако, как и лазерные сканеры, они по-прежнему ограничены 1D-кодами.

2D-имиджеры захватывают полное изображение и могут декодировать как 1D-штрих-коды, так и 2D-коды — QR-коды, DataMatrix, PDF417, — а также коды, показанные на экране, повреждённые или плохо напечатанные коды, которые лазер может отклонить, и во многих случаях штрих-коды под неудобным углом, для которых линейному сканеру потребовалось бы перенацеливание. Эта гибкость обходится несколько дороже, чем сканеры, работающие только с 1D.

Для обычного розничного прилавка или склада, работающего только со стандартными штрих-кодами товаров, часто достаточно 1D-лазера или линейного имиджера, и это дешевле. Любая среда, где нужно сканировать QR-коды, мобильные посадочные талоны или билеты, документы, удостоверяющие личность, либо повреждённые/грязные этикетки, нуждается в 2D-имиджере — всё чаще становящемся выбором по умолчанию по мере распространения 2D-кодов в разных отраслях.
TEXT,
                'uz' => <<<'TEXT'
Barcha shtrix-kod skanerlari kodlarni bir xil o'qimaydi, va noto'g'ri texnologiyani tanlash sekin skanerlash, tez-tez xato o'qish yoki aslida kerak bo'lgan kodlarni o'qiy olmaslikka olib keladi. Kundalik foydalanishda uchraydigan uchta asosiy tur — lazerli skanerlar, chiziqli (1D) imijerlar va 2D imijerlar.

Lazerli skanerlar shtrix-kod bo'ylab harakatlanuvchi lazer nurini ishlatib, aks etgan yorug'likni o'lchaydi va UPC, Code 128 kabi standart 1D (chiziqli) shtrix-kodlarni dekodlaydi. Ular tez, turli masofalarda ishonchli va arzon, ammo faqat 1D kodlarni o'qiy oladi — QR kod yoki DataMatrix kabi 2D kodlarni o'qiy olmaydi va odatda telefon ekranidan to'g'ridan-to'g'ri shtrix-kodni o'qiy olmaydi.

Chiziqli (1D) imijerlar lazer o'rniga kichik kamera sensoridan foydalanib, shtrix-kod tasvirini oladi va uni raqamli tarzda dekodlaydi. Ular lazerli skanerlar bilan bir xil 1D shtrix-kod turlarini o'qiydi, ko'pincha aniq mo'ljallashni talab qilmasdan, va harakatlanuvchi lazer mexanizmi bo'lmagani uchun tez-tez tushib ketadigan muhitlarda ancha chidamli bo'ladi. Biroq, lazerli skanerlar kabi, ular hamon faqat 1D kodlar bilan cheklangan.

2D imijerlar to'liq tasvirni oladi va ham 1D shtrix-kodlarni, ham 2D kodlarni — QR kodlar, DataMatrix, PDF417 — shuningdek ekranda ko'rsatilgan shtrix-kodlarni, lazer rad etishi mumkin bo'lgan shikastlangan yoki yomon bosilgan kodlarni va ko'p hollarda chiziqli skanerni qayta mo'ljallashni talab qiladigan noqulay burchakdagi shtrix-kodlarni dekodlashi mumkin. Bu moslashuvchanlik faqat 1D bilan ishlaydigan skanerlarga qaraganda sal yuqoriroq narxga tushadi.

Faqat standart mahsulot shtrix-kodlari bilan ishlaydigan oddiy chakana savdo peshtaxtasi yoki ombor uchun 1D lazer yoki chiziqli imijer ko'pincha yetarli va arzonroq. QR kodlarni, mobil qadam talonlarini yoki chiptalarni, shaxsni tasdiqlovchi hujjatlarni yoki shikastlangan/iflos yorliqlarni skanerlash kerak bo'lgan har qanday muhitga 2D imijer kerak — turli sohalarda 2D kodlar tobora ko'proq tarqalgan sari bu tobora ko'proq standart tanlovga aylanmoqda.
TEXT,
            ],
        ];
    }

    private function ruggedPdaArticle(): array
    {
        return [
            'slug' => [
                'en' => 'choosing-rugged-pda-buyers-guide',
                'ru' => 'kak-vybrat-zashchishchenny-tsd',
                'uz' => 'chidamli-pda-tanlash-qollanma',
            ],
            'title' => [
                'en' => 'Choosing a Rugged PDA / Mobile Computer: A Buyer\'s Guide',
                'ru' => 'Как выбрать защищённый ТСД: руководство покупателя',
                'uz' => 'Chidamli PDA/mobil kompyuterni tanlash: xaridor uchun qo\'llanma',
            ],
            'excerpt' => [
                'en' => 'Android or Windows, what an IP rating actually means, and how to size the right device for your team.',
                'ru' => 'Android или Windows, что на самом деле означает степень защиты IP, и как подобрать устройство под задачи команды.',
                'uz' => 'Android yoki Windows, IP darajasi aslida nimani anglatadi va jamoangiz uchun to\'g\'ri qurilmani qanday tanlash kerak.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Rugged mobile computers (often still called PDAs, though most now resemble oversized smartphones with a built-in scanner) are a bigger purchase decision than they first appear, since the right or wrong choice affects a warehouse or field team's daily productivity for years. Four factors matter most: operating system, ruggedness rating, screen and keypad, and battery life.

Operating system: Android now dominates new rugged device releases, offering a familiar interface and access to standard mobile apps, while Windows-based devices (once the industry standard) remain common in legacy fleets and certain industrial software ecosystems that were never rebuilt for Android. If you're deploying new software or integrating with modern warehouse management systems, Android is almost always the safer long-term choice today; if you're adding devices to an existing Windows-based fleet, matching the OS avoids a costly parallel-support burden.

Ruggedness rating: look for an IP (Ingress Protection) rating, written as IP-something like IP65 or IP67 — the first digit is dust resistance (6 is fully dust-tight), the second is water resistance (5 withstands water jets, 7 withstands brief submersion). Separately, look for a drop rating in meters (e.g., "1.2m to concrete") and how many drops it's rated to survive — this matters more than IP rating for a device that gets dropped on a warehouse floor daily.

Screen and input: a larger screen helps with data-heavy apps and touch accuracy in gloves, but adds bulk and weight for all-day carrying; a physical keypad remains preferred in some logistics and cold-storage environments where gloved touchscreen use is unreliable.

Battery life should be rated for a full shift with the scanner and screen in active use, not just standby — and hot-swappable batteries are worth the extra cost for any 24/7 or multi-shift operation, since charging downtime is lost productivity.
TEXT,
                'ru' => <<<'TEXT'
Защищённые мобильные компьютеры (часто их всё ещё называют ТСД, хотя большинство теперь похожи на увеличенные смартфоны со встроенным сканером) — более серьёзное решение о покупке, чем кажется на первый взгляд, поскольку правильный или неправильный выбор влияет на ежедневную продуктивность складской или полевой команды на годы вперёд. Важнее всего четыре фактора: операционная система, степень защищённости, экран и клавиатура, а также время автономной работы.

Операционная система: Android сейчас доминирует среди новых защищённых устройств, предлагая привычный интерфейс и доступ к стандартным мобильным приложениям, тогда как устройства на Windows (некогда отраслевой стандарт) остаются распространены в устаревших парках техники и в некоторых промышленных программных экосистемах, которые так и не были переписаны под Android. Если вы развёртываете новое ПО или интегрируетесь с современными системами управления складом, Android сегодня почти всегда более безопасный долгосрочный выбор; если вы пополняете существующий парк на Windows, совпадение ОС избавляет от дорогостоящей нагрузки параллельной поддержки.

Степень защищённости: ищите рейтинг IP (Ingress Protection), записываемый как IP с цифрами, например IP65 или IP67 — первая цифра означает пылезащиту (6 — полная пыленепроницаемость), вторая — влагозащиту (5 выдерживает струи воды, 7 — кратковременное погружение). Отдельно смотрите на рейтинг падения в метрах (например, «1,2 м на бетон») и сколько падений устройство выдерживает — для устройства, которое ежедневно роняют на складской пол, это важнее рейтинга IP.

Экран и ввод: больший экран помогает с приложениями, насыщенными данными, и точностью касания в перчатках, но добавляет габариты и вес при ношении весь день; физическая клавиатура по-прежнему предпочтительна в некоторых логистических и холодильных средах, где использование сенсорного экрана в перчатках ненадёжно.

Время автономной работы должно быть рассчитано на полную смену при активном использовании сканера и экрана, а не только в режиме ожидания — а горячая замена батарей стоит дополнительных затрат для любой круглосуточной или многосменной работы, поскольку время на зарядку — это потерянная продуктивность.
TEXT,
                'uz' => <<<'TEXT'
Chidamli mobil kompyuterlar (ko'pincha hali ham PDA deb ataladi, garchi ularning ko'pchiligi endi o'rnatilgan skanerli kattalashtirilgan smartfonlarga o'xshasa ham) dastlab ko'ringanidan jiddiyroq xarid qarori hisoblanadi, chunki to'g'ri yoki noto'g'ri tanlov ombor yoki dala jamoasining kundalik samaradorligiga yillar davomida ta'sir qiladi. Eng muhim to'rt omil: operatsion tizim, chidamlilik darajasi, ekran va klaviatura, hamda batareya quvvati.

Operatsion tizim: Android hozirda yangi chidamli qurilmalar orasida ustunlik qilmoqda, tanish interfeys va standart mobil ilovalarga kirish imkonini beradi, Windows asosidagi qurilmalar esa (bir vaqtlar soha standarti) eski qurilmalar parkida va Android uchun hech qachon qayta qurilmagan ba'zi sanoat dasturiy ta'minoti ekotizimlarida hamon uchraydi. Agar yangi dasturiy ta'minotni joriy qilayotgan yoki zamonaviy ombor boshqaruvi tizimlari bilan integratsiya qilayotgan bo'lsangiz, bugungi kunda Android deyarli har doim xavfsizroq uzoq muddatli tanlov; agar mavjud Windows asosidagi parkka qurilmalar qo'shayotgan bo'lsangiz, OT ni moslashtirish qimmatga tushadigan parallel qo'llab-quvvatlash yukidan qutqaradi.

Chidamlilik darajasi: IP (Ingress Protection) darajasini qidiring, IP65 yoki IP67 kabi raqamlar bilan yoziladi — birinchi raqam changga chidamlilik (6 — to'liq changdan himoyalangan), ikkinchisi — namlikka chidamlilik (5 suv oqimiga, 7 qisqa muddatli suvga tushishga chidaydi). Alohida, metrlarda tushish darajasini (masalan, "betonga 1,2 m") va qurilma necha marta tushishga chidashini tekshiring — har kuni ombor poliga tushib turadigan qurilma uchun bu IP darajasidan ko'ra muhimroq.

Ekran va kiritish: kattaroq ekran ma'lumotga boy ilovalar va qo'lqopda teginish aniqligiga yordam beradi, ammo kun bo'yi ko'tarib yurish uchun hajm va og'irlik qo'shadi; jismoniy klaviatura esa qo'lqopda sensorli ekrandan foydalanish ishonchsiz bo'lgan ba'zi logistika va sovutgich omborlarida hamon afzal ko'riladi.

Batareya quvvati skaner va ekran faol ishlatilganda to'liq smenaga yetishi kerak, shunchaki kutish rejimida emas — issiq almashtiriladigan batareyalar esa har qanday kechayu-kunduz yoki ko'p smenali ish uchun qo'shimcha xarajatga arziydi, chunki zaryadlash vaqti — yo'qotilgan samaradorlikdir.
TEXT,
            ],
        ];
    }

    private function labelMaterialArticle(): array
    {
        return [
            'slug' => [
                'en' => 'barcode-label-material-guide',
                'ru' => 'materialy-etiketok-dlya-shtrih-koda',
                'uz' => 'shtrix-kod-yorliqlari-materiallari',
            ],
            'title' => [
                'en' => 'Barcode Label Material Guide: Paper vs. Polypropylene vs. Polyester',
                'ru' => 'Материалы этикеток для штрих-кода: бумага, полипропилен, полиэстер',
                'uz' => 'Shtrix-kod yorliqlari materiallari qo\'llanmasi: qog\'oz, polipropilen, poliester',
            ],
            'excerpt' => [
                'en' => 'The label stock matters as much as the ribbon — here\'s which material fits which environment.',
                'ru' => 'Материал этикетки важен не меньше ленты — какой материал подходит для какой среды.',
                'uz' => 'Yorliq materiali lenta kabi muhim — qaysi material qaysi muhitga mos keladi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
Ribbon chemistry gets most of the attention, but the label material itself is just as important to how long a print survives — and the two need to be chosen together, since not every ribbon works on every label stock.

Paper labels are the most common and least expensive option, suitable for indoor use, short-to-medium shelf life, and situations without exposure to moisture, chemicals, or abrasion. Uncoated paper pairs well with wax ribbons; coated ("semi-gloss") paper generally needs at least a wax-resin ribbon to hold the print properly.

Polypropylene (PP) labels are a synthetic film, resistant to moisture, tearing, and many chemicals, while remaining relatively low-cost compared to other synthetics. They're a common choice for products that might get wet or handled roughly — outdoor retail displays, cosmetics and personal care products, and drum or container labeling. Polypropylene requires a resin or high-quality wax-resin ribbon; standard wax will not bond properly to a synthetic surface.

Polyester (PET) labels are the most durable common option, resistant to extreme temperatures, UV exposure, chemicals, and abrasion, making them the standard for asset tags, compliance and safety labels, and anything that needs to survive years outdoors or in industrial environments. Polyester requires a resin ribbon — nothing less will produce a durable bond on this material.

When choosing label stock, start from the environment the label will actually live in (indoor/outdoor, dry/wet, short/long lifespan, handling conditions) rather than the cheapest option, then select a ribbon chemistry rated for that specific material — sellers list both ribbon and label compatibility, and matching them correctly the first time avoids reordering and wasted stock.
TEXT,
                'ru' => <<<'TEXT'
Составу ленты уделяется больше всего внимания, но сам материал этикетки не менее важен для того, сколько проживёт отпечаток, — и выбирать их нужно вместе, поскольку не каждая лента работает с каждым материалом этикетки.

Бумажные этикетки — самый распространённый и недорогой вариант, подходящий для использования в помещении, короткого-среднего срока хранения и ситуаций без воздействия влаги, химикатов или истирания. Немелованная бумага хорошо сочетается с wax-лентами; мелованная («полуглянцевая») бумага обычно требует как минимум wax-resin ленты, чтобы отпечаток держался как следует.

Полипропиленовые (PP) этикетки — это синтетическая плёнка, устойчивая к влаге, разрыву и многим химикатам, при этом остающаяся относительно недорогой по сравнению с другими синтетиками. Это распространённый выбор для товаров, которые могут намокнуть или подвергаться грубому обращению, — уличных розничных витрин, косметики и товаров личной гигиены, маркировки бочек или контейнеров. Полипропилен требует resin или качественной wax-resin ленты; стандартный wax не сцепится должным образом с синтетической поверхностью.

Полиэстеровые (PET) этикетки — самый долговечный распространённый вариант, устойчивый к экстремальным температурам, УФ-излучению, химикатам и истиранию, что делает их стандартом для бирок активов, этикеток соответствия требованиям безопасности и всего, что должно прослужить годы на улице или в промышленных условиях. Полиэстер требует resin ленты — ничто менее прочное не даст долговечного сцепления с этим материалом.

При выборе материала этикетки отталкивайтесь от реальной среды, где она будет использоваться (в помещении/на улице, сухо/влажно, короткий/долгий срок службы, условия обращения), а не от самого дешёвого варианта, а затем подбирайте состав ленты, рассчитанный именно на этот материал — продавцы указывают совместимость и ленты, и этикетки, и правильное сопоставление с первого раза избавляет от повторных заказов и напрасно потраченного материала.
TEXT,
                'uz' => <<<'TEXT'
Lenta tarkibiga ko'proq e'tibor qaratiladi, ammo yorliqning o'z materiali bosmaning qancha yashashida xuddi shunchalik muhim — va ularni birgalikda tanlash kerak, chunki har bir lenta har bir yorliq materialida ishlayvermaydi.

Qog'oz yorliqlar eng keng tarqalgan va eng arzon variant bo'lib, xona ichida foydalanish, qisqa-o'rta muddatli saqlash va namlik, kimyoviy moddalar yoki ishqalanish ta'siri bo'lmagan holatlar uchun mos keladi. Qoplanmagan qog'oz vaks lentalar bilan yaxshi mos keladi; qoplangan ("yarim yaltiroq") qog'oz esa odatda bosmani to'g'ri ushlab turish uchun kamida vaks-rezin lentani talab qiladi.

Polipropilen (PP) yorliqlar — namlik, yirtilish va ko'plab kimyoviy moddalarga chidamli, boshqa sintetikalarga nisbatan nisbatan arzon bo'lgan sintetik plyonka. Ular ho'l bo'lishi yoki qo'pol muomala qilinishi mumkin bo'lgan mahsulotlar uchun keng tarqalgan tanlov — ochiq havodagi chakana savdo vitrinalari, kosmetika va shaxsiy gigiena mahsulotlari, bochka yoki konteynerlarni belgilash. Polipropilen rezin yoki yuqori sifatli vaks-rezin lentani talab qiladi; standart vaks sintetik yuzaga to'g'ri yopishmaydi.

Poliester (PET) yorliqlar eng chidamli keng tarqalgan variant bo'lib, ekstremal haroratlarga, UB nurlanishiga, kimyoviy moddalarga va ishqalanishga chidamli, bu ularni aktiv belgilari, muvofiqlik va xavfsizlik yorliqlari hamda yillar davomida ochiq havoda yoki sanoat sharoitida xizmat qilishi kerak bo'lgan har qanday narsa uchun standart qiladi. Poliester rezin lentani talab qiladi — bundan kam narsa bu materialda chidamli yopishishni bermaydi.

Yorliq materialini tanlashda eng arzon variantdan emas, yorliq haqiqatan foydalaniladigan muhitdan (xona ichi/tashqarisi, quruq/nam, qisqa/uzoq muddat, muomala sharoitlari) boshlang, so'ngra aynan shu material uchun mo'ljallangan lenta tarkibini tanlang — sotuvchilar ham lenta, ham yorliq mosligini ko'rsatadi, va ularni birinchi martadan to'g'ri moslashtirish qayta buyurtma berish va behuda sarflangan materialdan saqlaydi.
TEXT,
            ],
        ];
    }

    private function barcodeTypesArticle(): array
    {
        return [
            'slug' => [
                'en' => '1d-vs-2d-barcodes-and-rfid',
                'ru' => 'shtrih-kody-1d-2d-i-rfid',
                'uz' => '1d-va-2d-shtrix-kodlar-va-rfid',
            ],
            'title' => [
                'en' => '1D vs. 2D Barcodes (and Where RFID Fits)',
                'ru' => 'Штрих-коды 1D и 2D (и при чём здесь RFID)',
                'uz' => '1D va 2D shtrix-kodlar (va RFID qayerda o\'rin oladi)',
            ],
            'excerpt' => [
                'en' => 'A short, non-technical guide to the identification technologies you\'ll encounter, and what each is actually good for.',
                'ru' => 'Короткое нетехническое руководство по технологиям идентификации, с которыми вы столкнётесь, и для чего на самом деле нужна каждая.',
                'uz' => 'Duch keladigan identifikatsiya texnologiyalari haqida qisqa, texnik bo\'lmagan qo\'llanma va har biri aslida nima uchun yaxshi.',
            ],
            'body' => [
                'en' => <<<'TEXT'
"Barcode" covers several genuinely different technologies, and knowing the difference helps when specifying what a printer, scanner, or label actually needs to support.

1D (linear) barcodes — UPC, EAN, Code 128, Code 39 — are the familiar series of vertical bars most people picture when they hear "barcode." They store a relatively small amount of data (typically a product or item number that looks up full details in a database) and need to be scanned in a straight line across the code. They're simple, cheap to print, and universally supported, which is why they remain the standard for retail product identification decades after their introduction.

2D barcodes — QR codes, DataMatrix, PDF417 — store data in a two-dimensional pattern, which lets them hold far more information (a URL, contact details, or a large ID string) in a smaller physical space, and they can be scanned from any angle rather than needing a straight-line pass. DataMatrix is common on small industrial parts and pharmaceutical packaging where space is limited; QR codes dominate consumer-facing use (marketing, payments, boarding passes) because phone cameras read them natively.

RFID (Radio Frequency Identification) is a different category entirely — not a barcode at all, but a tag containing a small chip and antenna that a reader can detect via radio waves without needing a direct line of sight, and often without the tag being visible or even accessible. This makes RFID valuable for scanning many items at once (a whole pallet, without unpacking it) or tracking items that move through automated points without a person present to scan them, at a materially higher cost per tag than a printed barcode.

The practical takeaway for most buyers: use 1D for standard product identification, 2D when you need more data density or scanning at odd angles, and consider RFID only when the specific problem is bulk/automated reading rather than data capacity — for the overwhelming majority of labeling needs, a well-chosen barcode remains simpler and cheaper.
TEXT,
                'ru' => <<<'TEXT'
«Штрих-код» объединяет несколько по-настоящему разных технологий, и знание разницы помогает при определении того, что именно должен поддерживать принтер, сканер или этикетка.

1D (линейные) штрих-коды — UPC, EAN, Code 128, Code 39 — это привычный ряд вертикальных полос, который большинство представляет при слове «штрих-код». Они хранят относительно небольшой объём данных (обычно номер товара или позиции, по которому полная информация ищется в базе данных) и должны сканироваться по прямой линии через код. Они простые, дёшевы в печати и поддерживаются повсеместно, поэтому остаются стандартом идентификации розничных товаров спустя десятилетия после появления.

2D-штрих-коды — QR-коды, DataMatrix, PDF417 — хранят данные в двумерном узоре, что позволяет вместить намного больше информации (URL, контактные данные или длинную строку идентификатора) на меньшей физической площади, и их можно сканировать под любым углом, а не только по прямой. DataMatrix распространён на мелких промышленных деталях и фармацевтической упаковке, где место ограничено; QR-коды доминируют в потребительском использовании (маркетинг, платежи, посадочные талоны), потому что камеры телефонов считывают их нативно.

RFID (радиочастотная идентификация) — совершенно другая категория, вообще не штрих-код, а метка с небольшим чипом и антенной, которую считыватель может обнаружить по радиоволнам без необходимости прямой видимости, и часто без того, чтобы метка была видна или даже доступна. Это делает RFID ценным для одновременного сканирования множества предметов (целого паллета без распаковки) или отслеживания предметов, проходящих через автоматизированные точки без человека для сканирования, при заметно более высокой стоимости на метку, чем у напечатанного штрих-кода.

Практический вывод для большинства покупателей: используйте 1D для стандартной идентификации товаров, 2D — когда нужна большая плотность данных или сканирование под неудобным углом, а RFID рассматривайте только тогда, когда конкретная задача — это массовое/автоматизированное считывание, а не ёмкость данных: для подавляющего большинства задач маркировки удачно подобранный штрих-код остаётся проще и дешевле.
TEXT,
                'uz' => <<<'TEXT'
"Shtrix-kod" bir necha haqiqatan farqli texnologiyalarni qamrab oladi, va farqni bilish printer, skaner yoki yorliq aslida nimani qo'llab-quvvatlashi kerakligini belgilashda yordam beradi.

1D (chiziqli) shtrix-kodlar — UPC, EAN, Code 128, Code 39 — ko'pchilik "shtrix-kod" so'zini eshitganda ko'z oldiga keltiradigan tanish vertikal chiziqlar qatoridir. Ular nisbatan kichik hajmdagi ma'lumotni saqlaydi (odatda to'liq ma'lumotlar bazadan qidiriladigan mahsulot yoki band raqami) va kod bo'ylab to'g'ri chiziq bo'ylab skanerlanishi kerak. Ular sodda, bosib chiqarishda arzon va universal qo'llab-quvvatlanadi, shuning uchun paydo bo'lganidan o'nlab yillar o'tsa ham chakana savdo mahsulotlarini identifikatsiya qilish standarti bo'lib qolmoqda.

2D shtrix-kodlar — QR kodlar, DataMatrix, PDF417 — ma'lumotni ikki o'lchamli naqshda saqlaydi, bu esa kichikroq jismoniy maydonda ancha ko'proq ma'lumotni (URL, aloqa ma'lumotlari yoki uzun identifikator qatori) sig'dirish imkonini beradi, va ular to'g'ri chiziq bo'ylab emas, istalgan burchakdan skanerlanishi mumkin. DataMatrix joy cheklangan kichik sanoat qismlari va farmatsevtika qadoqlarida keng tarqalgan; QR kodlar esa iste'molchiga qaratilgan foydalanishda (marketing, to'lovlar, qadam talonlari) ustunlik qiladi, chunki telefon kameralari ularni tabiiy ravishda o'qiydi.

RFID (Radio Frequency Identification — radiochastota orqali identifikatsiya) esa mutlaqo boshqa toifa — umuman shtrix-kod emas, balki o'quvchi qurilma to'g'ridan-to'g'ri ko'rish chizig'isiz, ko'pincha yorliq ko'rinmasa yoki hatto qo'l yetmasa ham, radioto'lqinlar orqali aniqlay oladigan kichik chip va antennaga ega yorliqdir. Bu RFID ni bir vaqtning o'zida ko'plab narsalarni skanerlash (butun paletni qadoqdan chiqarmasdan) yoki odam skanerlash uchun bo'lmagan avtomatlashtirilgan nuqtalar orqali o'tayotgan narsalarni kuzatish uchun qimmatli qiladi, ammo bosilgan shtrix-kodga qaraganda har bir yorliq uchun sezilarli darajada yuqori narxda.

Ko'pchilik xaridorlar uchun amaliy xulosa: standart mahsulot identifikatsiyasi uchun 1D dan, ko'proq ma'lumot zichligi yoki noqulay burchakdan skanerlash kerak bo'lganda 2D dan foydalaning, RFID ni esa faqat aniq muammo ma'lumot sig'imi emas, balki ommaviy/avtomatlashtirilgan o'qish bo'lganda ko'rib chiqing — bu ehtiyojlarning katta qismi uchun to'g'ri tanlangan shtrix-kod sodda va arzon bo'lib qoladi.
TEXT,
            ],
        ];
    }
}
