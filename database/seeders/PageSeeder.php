<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use App\Services\NavMenu;
use Illuminate\Database\Seeder;

/**
 * The five system CMS pages (about, for-sellers, privacy, terms, contact)
 * with starter copy in uz/ru/en. Reference data, idempotent and keyed on
 * `key`: firstOrCreate, so re-running `db:seed` never overwrites text an
 * admin has since edited. Privacy and Terms are PLACEHOLDERS clearly marked
 * for legal review — they must be replaced before public launch.
 *
 * DatabaseSeeder uses WithoutModelEvents, so Page's saving hook (body
 * sanitizing) and the nav cache flush are done explicitly here.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $updatedBy = User::where('email', config('ribbon.super_admin.email'))->value('id');

        foreach ($this->pages() as $key => $data) {
            Page::firstOrCreate(['key' => $key], [
                'slug' => null,
                'title' => $data['title'],
                'lead' => $data['lead'],
                'body' => array_map(Page::sanitizeBody(...), $data['body']),
                'meta_description' => $data['meta_description'],
                'is_published' => true,
                'show_in_header' => $data['header'],
                'show_in_footer' => $data['footer'],
                'footer_column' => $data['column'],
                'sort_order' => $data['sort'],
                'updated_by' => $updatedBy,
            ]);
        }

        NavMenu::flush();

        $this->command?->info('Pages seeded: '.count($this->pages()).' system pages.');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function pages(): array
    {
        $register = route('sellers.register', absolute: false);

        return [
            'about' => [
                'header' => true, 'footer' => true, 'column' => 'company', 'sort' => 10,
                'title' => ['uz' => 'Ribbon haqida', 'ru' => 'О Ribbon', 'en' => 'About Ribbon'],
                'lead' => [
                    'uz' => 'Ribbon — avto-identifikatsiya sohasidagi B2B bozor: termotransfer lentalar, printerlar, skanerlar va aksessuarlar.',
                    'ru' => 'Ribbon — B2B-маркетплейс для автоматической идентификации: термотрансферные ленты, принтеры, сканеры и аксессуары.',
                    'en' => 'Ribbon is a B2B marketplace for the auto-ID industry: thermal transfer ribbons, printers, scanners and accessories.',
                ],
                'meta_description' => [
                    'uz' => 'Ribbon — O\'zbekistondagi tasdiqlangan sotuvchilardan termotransfer lentalar va avto-ID uskunalarini topish uchun B2B bozor.',
                    'ru' => 'Ribbon — B2B-маркетплейс: термотрансферные ленты и оборудование автоматической идентификации от проверенных продавцов Узбекистана.',
                    'en' => 'Ribbon is a B2B marketplace to find thermal transfer ribbons and auto-ID equipment from verified sellers in Uzbekistan.',
                ],
                'body' => [
                    'uz' => '<p>Ribbon korxonalarga kerakli mahsulotni aniq xususiyatlari bo\'yicha topishga va bir nechta sotuvchiga bitta so\'rov yuborishga yordam beradi. Ro\'yxatdan o\'tish, savat va onlayn to\'lov yo\'q: siz tijorat taklifi so\'rovini yuborasiz yoki sotuvchiga to\'g\'ridan-to\'g\'ri qo\'ng\'iroq qilasiz.</p><h2>Nima uchun Ribbon</h2><p>Biz narxdan ko\'ra mutaxassislikni birinchi o\'ringa qo\'yamiz: sotuvchining tajribasi, ombordagi mavjudlik, ixtisosligi va assortimenti. Narx har doim ko\'rinadi, lekin tanlovdagi oxirgi omil bo\'lib qoladi.</p><ul><li>Toifa bo\'yicha aniq texnik filtrlar: eni, uzunligi, turi, printer modeli.</li><li>Tekshirilgan sotuvchilar va ularning haqiqiy narxlari, minimal buyurtma miqdori ko\'rsatilgan.</li><li>Tanlashga yordam beradigan maqolalar va yangiliklar.</li></ul><h2>Qanday ishlaydi</h2><p>Mahsulotni toping, tanlovga qo\'shing va telefon raqamingizni qoldiring. Sotuvchilar siz bilan bog\'lanib, narx va shartlarni o\'zingiz bilan kelishadi. Ribbon bitimlardan foiz olmaydi.</p>',
                    'ru' => '<p>Ribbon помогает компаниям найти нужный товар по точным характеристикам и отправить один запрос сразу нескольким продавцам. Здесь нет регистрации, корзины и онлайн-оплаты: вы отправляете запрос коммерческого предложения или звоните продавцу напрямую.</p><h2>Почему Ribbon</h2><p>Мы ставим экспертизу выше цены: опыт продавца, наличие на складе, специализация и ассортимент. Цена всегда видна, но остаётся последним фактором выбора.</p><ul><li>Точные технические фильтры по категориям: ширина, длина, тип, модель принтера.</li><li>Проверенные продавцы и их реальные цены с указанием минимального заказа.</li><li>Статьи и новости, которые помогают сделать выбор.</li></ul><h2>Как это работает</h2><p>Найдите товар, добавьте в подборку и оставьте номер телефона. Продавцы свяжутся с вами и согласуют цену и условия напрямую. Ribbon не берёт комиссию со сделок.</p>',
                    'en' => '<p>Ribbon helps businesses find the exact product by its specifications and send a single request to several sellers at once. There is no registration, cart or online payment: you send a commercial offer request or call a seller directly.</p><h2>Why Ribbon</h2><p>We put expertise ahead of price: seller experience, stock, specialization and assortment. Price is always visible but stays the last factor in the decision.</p><ul><li>Precise technical filters per category: width, length, type, printer model.</li><li>Verified sellers and their real prices, with the minimum order quantity shown.</li><li>Articles and news that help you choose.</li></ul><h2>How it works</h2><p>Find a product, add it to your selection and leave your phone number. Sellers contact you and agree the price and terms with you directly. Ribbon takes no commission on deals.</p>',
                ],
            ],
            'for-sellers' => [
                'header' => false, 'footer' => true, 'column' => 'company', 'sort' => 20,
                'title' => ['uz' => 'Sotuvchilar uchun', 'ru' => 'Продавцам', 'en' => 'For sellers'],
                'lead' => [
                    'uz' => 'Tayyor B2B xaridorlar oqimi va katalogning professional tuzilmasi — o\'z mahsulotlaringizni Ribbonda joylang.',
                    'ru' => 'Поток готовых B2B-покупателей и профессиональная структура каталога — размещайте свои товары на Ribbon.',
                    'en' => 'A stream of ready B2B buyers and a professional catalog structure: list your products on Ribbon.',
                ],
                'meta_description' => [
                    'uz' => 'Ribbonda sotuvchi bo\'ling: lentalar bepul, boshqa toifalar oylik obuna bilan. Xaridorlardan tijorat taklifi so\'rovlarini oling.',
                    'ru' => 'Станьте продавцом на Ribbon: ленты бесплатно, остальные категории по ежемесячной подписке. Получайте запросы коммерческих предложений.',
                    'en' => 'Become a seller on Ribbon: ribbons are free, other categories need a monthly subscription. Receive commercial offer requests from buyers.',
                ],
                'body' => [
                    'uz' => '<h2>Joylashtirish qanday ishlaydi</h2><p>Sotuvchi sifatida ariza topshirasiz, moderator STIR/INN va kompaniya kontaktlarini tekshiradi. Tasdiqlangach, har bir toifaning o\'z shakli orqali mahsulotlaringizni o\'zingiz kiritasiz: xususiyatlar, so\'mdagi narx va minimal buyurtma miqdori majburiy.</p><h2>Narxlar</h2><ul><li><strong>Termotransfer lentalar — bepul.</strong> Cheklovsiz joylashtirasiz.</li><li><strong>Boshqa toifalar</strong> (printerlar, skanerlar, PDA, aksessuarlar va boshqalar) <strong>oylik obuna</strong> talab qiladi. Obuna bitta, tariflarsiz, mahsulotlar soni cheklanmagan.</li></ul><h2>To\'lov</h2><p>Hozircha to\'lov qo\'lda: biz hisob-faktura yuboramiz, siz bank o\'tkazmasi orqali to\'laysiz, administrator obunani faollashtiradi. Avtomatik yechish yo\'q. Obuna tugasa, pullik toifadagi mahsulotlar 7 kunlik imtiyozli davrdan keyin vitrinadan yashiriladi va to\'lovdan so\'ng avtomatik qaytadi.</p><h2>Siz nima olasiz</h2><ul><li>Xaridorlardan tijorat taklifi so\'rovlari — faqat sizning mahsulotlaringiz bo\'yicha.</li><li>Tartibli, texnik jihatdan to\'liq katalog.</li><li>Obunachilar uchun: tahlillar, JSON mahsulot tasmasi va Telegram orqali so\'rov xabarlari.</li></ul><p>Reyting faqat haqiqiy ko\'rsatkichlar bo\'yicha shakllanadi; pulli reklama joylashuvi yo\'q.</p><p><a href="'.$register.'">Sotuvchi sifatida ro\'yxatdan o\'tish</a></p>',
                    'ru' => '<h2>Как работает размещение</h2><p>Вы подаёте заявку продавца, модератор проверяет ИНН и контакты компании. После одобрения вы самостоятельно добавляете товары через форму своей категории: характеристики, цена в сумах и минимальный заказ обязательны.</p><h2>Стоимость</h2><ul><li><strong>Термотрансферные ленты — бесплатно.</strong> Размещайте без ограничений.</li><li><strong>Остальные категории</strong> (принтеры, сканеры, КПК, аксессуары и другие) <strong>требуют ежемесячной подписки</strong>. Подписка одна, без тарифов, количество товаров не ограничено.</li></ul><h2>Оплата</h2><p>Пока оплата ручная: мы выставляем счёт, вы оплачиваете банковским переводом, администратор активирует подписку. Автоматических списаний нет. После окончания подписки товары платных категорий скрываются с витрины по истечении 7 дней льготного периода и возвращаются автоматически после оплаты.</p><h2>Что вы получаете</h2><ul><li>Запросы коммерческих предложений от покупателей — только по вашим товарам.</li><li>Упорядоченный каталог с полными техническими характеристиками.</li><li>Для подписчиков: аналитика, JSON-лента товаров и уведомления о запросах в Telegram.</li></ul><p>Рейтинг формируется только по реальным показателям; платного продвижения нет.</p><p><a href="'.$register.'">Зарегистрироваться как продавец</a></p>',
                    'en' => '<h2>How listing works</h2><p>You apply as a seller and a moderator checks your tax ID (INN) and company contacts. Once approved, you add your own products through your category\'s form: specifications, a price in UZS and a minimum order quantity are required.</p><h2>Pricing</h2><ul><li><strong>Thermal transfer ribbons are free.</strong> List without limits.</li><li><strong>Every other category</strong> (printers, scanners, PDAs, accessories and more) <strong>needs a monthly subscription</strong>. There is one plan, no tiers, and unlimited products.</li></ul><h2>Payment</h2><p>For now payment is manual: we issue an invoice, you pay by bank transfer and an administrator activates your subscription. There is no automatic charging. When a subscription lapses, products in paid categories are hidden from the storefront after a 7-day grace period and come back automatically once you renew.</p><h2>What you get</h2><ul><li>Commercial offer requests from buyers, only for your own products.</li><li>A structured catalog with full technical specifications.</li><li>For subscribers: analytics, a JSON product feed and Telegram alerts for new requests.</li></ul><p>Ranking is earned from real performance; there are no paid promotions.</p><p><a href="'.$register.'">Register as a seller</a></p>',
                ],
            ],
            'privacy' => [
                'header' => false, 'footer' => true, 'column' => 'legal', 'sort' => 10,
                'title' => ['uz' => 'Maxfiylik siyosati', 'ru' => 'Политика конфиденциальности', 'en' => 'Privacy policy'],
                'lead' => [
                    'uz' => 'Qoralama matn: nashrdan oldin yurist tomonidan ko\'rib chiqilishi shart.',
                    'ru' => 'Черновой текст: перед публикацией требуется проверка юристом.',
                    'en' => 'Draft text: must be reviewed by a lawyer before launch.',
                ],
                'meta_description' => [
                    'uz' => 'Ribbon xaridor va sotuvchilarning shaxsiy ma\'lumotlarini qanday yig\'ishi va ishlatishi haqida.',
                    'ru' => 'Как Ribbon собирает и использует персональные данные покупателей и продавцов.',
                    'en' => 'How Ribbon collects and uses personal data of buyers and sellers.',
                ],
                'body' => [
                    'uz' => '<p><strong>[Yuridik ko\'rib chiqish uchun placeholder. Bu matn yakuniy emas.]</strong></p><h2>Qanday ma\'lumotlarni yig\'amiz</h2><p>Tijorat taklifi so\'rovi yoki aloqa shaklida siz telefon raqamingizni (va ixtiyoriy ravishda kompaniya, e-pochta, xabar) qoldirasiz. Sotuvchilar uchun — kompaniya va hujjat ma\'lumotlari.</p><h2>Qanday ishlatamiz</h2><p>Telefon raqamingiz faqat so\'rovingizdagi mahsulotlarning sotuvchilariga va platforma ma\'muriyatiga ko\'rinadi va siz bilan bog\'lanish uchun ishlatiladi. Uni boshqa sotuvchilarga yoki uchinchi shaxslarga bermaymiz.</p><h2>Saqlash va so\'rovlar</h2><p>Ma\'lumotlarni o\'chirishni so\'rash uchun biz bilan bog\'laning.</p>',
                    'ru' => '<p><strong>[Заглушка для юридической проверки. Этот текст не окончательный.]</strong></p><h2>Какие данные мы собираем</h2><p>В запросе коммерческого предложения или форме обратной связи вы оставляете номер телефона (и по желанию компанию, e-mail, сообщение). У продавцов — данные о компании и документы.</p><h2>Как мы их используем</h2><p>Ваш телефон виден только продавцам товаров из вашего запроса и администрации платформы и используется для связи с вами. Мы не передаём его другим продавцам и третьим лицам.</p><h2>Хранение и запросы</h2><p>Чтобы запросить удаление данных, свяжитесь с нами.</p>',
                    'en' => '<p><strong>[Placeholder for legal review. This text is not final.]</strong></p><h2>What we collect</h2><p>In a commercial offer request or the contact form you leave your phone number (and optionally company, e-mail and a message). For sellers: company and document details.</p><h2>How we use it</h2><p>Your phone number is visible only to the sellers of the products in your request and to the platform administration, and is used to contact you. We do not share it with other sellers or third parties.</p><h2>Retention and requests</h2><p>To request deletion of your data, contact us.</p>',
                ],
            ],
            'terms' => [
                'header' => false, 'footer' => true, 'column' => 'legal', 'sort' => 20,
                'title' => ['uz' => 'Foydalanish shartlari', 'ru' => 'Условия использования', 'en' => 'Terms of use'],
                'lead' => [
                    'uz' => 'Qoralama matn: nashrdan oldin yurist tomonidan ko\'rib chiqilishi shart.',
                    'ru' => 'Черновой текст: перед публикацией требуется проверка юристом.',
                    'en' => 'Draft text: must be reviewed by a lawyer before launch.',
                ],
                'meta_description' => [
                    'uz' => 'Ribbon platformasidan xaridorlar va sotuvchilar uchun foydalanish shartlari.',
                    'ru' => 'Условия использования платформы Ribbon для покупателей и продавцов.',
                    'en' => 'Terms of use of the Ribbon platform for buyers and sellers.',
                ],
                'body' => [
                    'uz' => '<p><strong>[Yuridik ko\'rib chiqish uchun placeholder. Bu matn yakuniy emas.]</strong></p><h2>Platforma haqida</h2><p>Ribbon — xaridorlar va sotuvchilarni bog\'laydigan axborot platformasi. Ribbon sotuvchi emas va bitimlar tomoni emas; to\'lov va yetkazib berish shartlarini xaridor va sotuvchi o\'zaro kelishadi.</p><h2>Narxlar va takliflar</h2><p>Narxlarni sotuvchilar belgilaydi. Tijorat taklifi so\'rovi majburiyat yuklamaydi. Boshqa valyutadagi narxlar taxminiydir.</p><h2>Sotuvchilar</h2><p>Sotuvchi kiritgan ma\'lumotlarning to\'g\'riligi uchun javobgardir. Ribbon qoidalarni buzgan mahsulot yoki sotuvchini yashirish yoki to\'xtatish huquqini saqlab qoladi.</p>',
                    'ru' => '<p><strong>[Заглушка для юридической проверки. Этот текст не окончательный.]</strong></p><h2>О платформе</h2><p>Ribbon — информационная платформа, связывающая покупателей и продавцов. Ribbon не является продавцом и стороной сделок; условия оплаты и поставки покупатель и продавец согласуют между собой.</p><h2>Цены и предложения</h2><p>Цены устанавливают продавцы. Запрос коммерческого предложения не создаёт обязательств. Цены в других валютах приблизительные.</p><h2>Продавцы</h2><p>Продавец отвечает за достоверность внесённых данных. Ribbon вправе скрыть или приостановить товар или продавца при нарушении правил.</p>',
                    'en' => '<p><strong>[Placeholder for legal review. This text is not final.]</strong></p><h2>About the platform</h2><p>Ribbon is an information platform connecting buyers and sellers. Ribbon is not a seller and not a party to deals; payment and delivery terms are agreed directly between buyer and seller.</p><h2>Prices and offers</h2><p>Prices are set by sellers. A commercial offer request creates no obligation. Prices shown in other currencies are approximate.</p><h2>Sellers</h2><p>Sellers are responsible for the accuracy of the data they enter. Ribbon may hide or suspend a product or seller that breaks the rules.</p>',
                ],
            ],
            'contact' => [
                // Intro copy for /contact only; never a menu link.
                'header' => false, 'footer' => false, 'column' => 'company', 'sort' => 30,
                'title' => ['uz' => 'Biz bilan bog\'laning', 'ru' => 'Свяжитесь с нами', 'en' => 'Contact us'],
                'lead' => [
                    'uz' => 'Savolingiz bormi yoki kerakli mahsulotni topa olmadingizmi? Telefon raqamingizni qoldiring, biz sizga qo\'ng\'iroq qilamiz.',
                    'ru' => 'Есть вопрос или не нашли нужный товар? Оставьте номер телефона, и мы вам перезвоним.',
                    'en' => 'Have a question or can\'t find a product? Leave your phone number and we will call you back.',
                ],
                'meta_description' => [
                    'uz' => 'Ribbon bilan bog\'laning: telefon, e-pochta, Telegram va qayta qo\'ng\'iroq so\'rovi.',
                    'ru' => 'Свяжитесь с Ribbon: телефон, e-mail, Telegram и форма обратного звонка.',
                    'en' => 'Contact Ribbon: phone, e-mail, Telegram and a call-back form.',
                ],
                'body' => ['uz' => '', 'ru' => '', 'en' => ''],
            ],
        ];
    }
}
