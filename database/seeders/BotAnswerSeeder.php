<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\BotAnswer;
use Illuminate\Database\Seeder;

/**
 * Starter answers for the rule-based chat bot (no AI). Created only when
 * missing (matched by `key`), so staff edits in Admin are never overwritten.
 * The bot stays switched off until staff turn it on. Russian is reviewed;
 * Uzbek and English were drafted by the developer and need a native check.
 */
class BotAnswerSeeder extends Seeder
{
    public function run(): void
    {
        $answers = [];

        foreach ($this->answers() as $key => $answer) {
            $answers[$key] = BotAnswer::firstOrCreate(['key' => $key], $answer + ['is_active' => true]);
        }

        $guide = Article::where('slug->en', 'thermal-transfer-ribbon-guide')->first();

        if ($guide && empty($answers['ink-side']->buttons)) {
            $answers['ink-side']->update(['buttons' => [
                $this->button('article', (string) $guide->id, ['uz' => "Qo'llanmani o'qish", 'ru' => 'Читать руководство', 'en' => 'Read the guide']),
                $this->button('finder', '', ['uz' => 'Lenta tanlash', 'ru' => 'Подобрать ленту', 'en' => 'Find a ribbon']),
            ]]);
        }

        $start = BotAnswer::firstOrCreate(['key' => 'start'], [
            'name' => 'Start menu',
            'reply' => [
                'uz' => "Assalomu alaykum! Men Ribbon yordamchi botiman. Mahsulot tanlashda yordam beraman yoki ko'p beriladigan savollarga javob beraman. Variantni tanlang yoki savolingizni yozing.",
                'ru' => 'Здравствуйте! Я бот-помощник Ribbon. Помогу подобрать товар или отвечу на частые вопросы. Выберите вариант или напишите свой вопрос.',
                'en' => 'Hello! I am the Ribbon help bot. I can help you find a product or answer common questions. Pick an option or type your question.',
            ],
            'keywords' => [
                'uz' => ['salom', 'assalomu alaykum', 'menyu', 'boshlash'],
                'ru' => ['привет', 'здравствуйте', 'меню', 'начать'],
                'en' => ['hello', 'hi', 'menu', 'start'],
            ],
            'buttons' => [
                $this->button('finder', '', ['uz' => 'Mahsulot tanlash', 'ru' => 'Подобрать товар', 'en' => 'Find a product']),
                $this->button('answer', (string) $answers['how-it-works']->id, ['uz' => 'Ribbon qanday ishlaydi', 'ru' => 'Как работает Ribbon', 'en' => 'How Ribbon works']),
                $this->button('answer', (string) $answers['prices']->id, ['uz' => 'Narxlar', 'ru' => 'Цены', 'en' => 'Prices']),
                $this->button('answer', (string) $answers['payment']->id, ['uz' => "To'lov", 'ru' => 'Оплата', 'en' => 'Payment']),
                $this->button('answer', (string) $answers['delivery']->id, ['uz' => 'Yetkazib berish', 'ru' => 'Доставка', 'en' => 'Delivery']),
            ],
            'is_active' => true,
        ]);

        if (! BotAnswer::where('is_start', true)->exists()) {
            $start->makeStart();
        }
    }

    private function answers(): array
    {
        return [
            'how-it-works' => [
                'name' => 'How Ribbon works',
                'reply' => [
                    'uz' => "Ribbon — avto-identifikatsiya sohasi uchun B2B katalog. Mahsulotni xususiyatlari bo'yicha topasiz, tanlovga qo'shasiz va telefon raqamingiz bilan tijorat taklifi so'rovini yuborasiz. Sotuvchi siz bilan to'g'ridan-to'g'ri bog'lanadi. Ro'yxatdan o'tish shart emas.",
                    'ru' => 'Ribbon — B2B-каталог для отрасли автоидентификации. Вы находите товар по характеристикам, добавляете его в подборку и отправляете запрос на коммерческое предложение с номером телефона. Продавец свяжется с вами напрямую. Регистрация не нужна.',
                    'en' => 'Ribbon is a B2B catalog for the auto-ID industry. You find a product by its specs, add it to your selection and send a request for a commercial offer with your phone number. The seller contacts you directly. No sign-up is needed.',
                ],
                'keywords' => [
                    'uz' => ['ribbon qanday ishlaydi', 'sayt qanday ishlaydi', 'qanday sotib olish', 'buyurtma berish', 'ribbon nima'],
                    'ru' => ['как работает ribbon', 'как работает сайт', 'как купить', 'как заказать', 'что такое ribbon'],
                    'en' => ['how ribbon works', 'how does ribbon work', 'how to buy', 'how to order', 'what is ribbon'],
                ],
                'buttons' => [
                    $this->button('finder', '', ['uz' => 'Mahsulot tanlash', 'ru' => 'Подобрать товар', 'en' => 'Find a product']),
                ],
            ],
            'prices' => [
                'name' => 'Prices',
                'reply' => [
                    'uz' => "Ribbon'dagi har bir mahsulotda sotuvchining bir dona uchun so'mdagi narxi va minimal buyurtma ko'rsatilgan. Aniq summani sotuvchi tijorat taklifida aytadi.",
                    'ru' => 'У каждого товара на Ribbon указана цена продавца в сумах за единицу и минимальный заказ. Точную сумму продавец назовёт в коммерческом предложении.',
                    'en' => "Every product on Ribbon shows the seller's price per unit in UZS and the minimum order. The seller confirms the exact total in their commercial offer.",
                ],
                'keywords' => [
                    'uz' => ['narx', 'narxi', 'qancha turadi', 'necha pul'],
                    'ru' => ['цена', 'цены', 'стоимость', 'сколько стоит', 'сколько стоят', 'почём', 'почем', 'прайс'],
                    'en' => ['price', 'cost', 'how much', 'price list'],
                ],
                'buttons' => [
                    $this->button('finder', '', ['uz' => 'Mahsulot tanlash', 'ru' => 'Подобрать товар', 'en' => 'Find a product']),
                ],
            ],
            'payment' => [
                'name' => 'Payment',
                'reply' => [
                    'uz' => "Ribbon'da onlayn to'lov va savat yo'q. To'lov va shartlarni sotuvchi bilan to'g'ridan-to'g'ri kelishasiz: o'tkazma, naqd pul yoki karta. Har bir sotuvchining usullari mahsulot sahifasida ko'rsatilgan.",
                    'ru' => 'На Ribbon нет онлайн-оплаты и корзины. Оплату и условия вы согласуете напрямую с продавцом: перечисление, наличные или карта. Способы оплаты каждого продавца указаны на странице товара.',
                    'en' => "Ribbon has no online payment or cart. You agree payment and terms directly with the seller: bank transfer, cash or card. Each seller's payment methods are shown on the product page.",
                ],
                'keywords' => [
                    'uz' => ["to'lov", 'karta', "pul o'tkazish", "onlayn to'lov"],
                    'ru' => ['оплата', 'оплатить', 'карта', 'перечисление', 'онлайн оплата'],
                    'en' => ['payment', 'pay', 'card', 'bank transfer'],
                ],
                'buttons' => [],
            ],
            'delivery' => [
                'name' => 'Delivery',
                'reply' => [
                    'uz' => "Yetkazib berishni Ribbon tashkil qilmaydi: muddat va narxni sotuvchi so'rovingizga javob berganda kelishasiz. Sotuvchining shahri uning ma'lumotlarida ko'rsatilgan.",
                    'ru' => 'Доставку Ribbon не организует: сроки и стоимость вы обсуждаете с продавцом, когда он ответит на запрос. Город продавца указан в его карточке.',
                    'en' => "Ribbon does not arrange delivery: you agree timing and cost with the seller when they reply to your request. The seller's city is shown in their details.",
                ],
                'keywords' => [
                    // The "... cost" pairs outscore the shorter price keywords ("сколько стоит доставка" is about delivery).
                    'uz' => ['yetkazib berish', 'yetkazish', 'dostavka', 'olib kelish', 'yetkazib berish narxi', 'dostavka narxi'],
                    'ru' => ['доставка', 'доставить', 'привезти', 'самовывоз', 'стоит доставка', 'стоимость доставки', 'цена доставки'],
                    'en' => ['delivery', 'shipping', 'deliver', 'delivery cost', 'shipping cost', 'cost of delivery'],
                ],
                'buttons' => [],
            ],
            'ink-side' => [
                'name' => 'Ink In / Ink Out',
                'reply' => [
                    'uz' => "Ink In — bo'yoq lentaning ichki tomonida, Ink Out — tashqi tomonida. Qaysi biri kerakligini printer belgilaydi: modelingiz qo'llanmasini tekshiring. Tez tekshirish: yopishqoq qog'ozni rulonning tashqi tomoniga bosing. Bo'yoq qolsa, bu Ink Out.",
                    'ru' => 'Ink In — краска на внутренней стороне ленты, Ink Out — на наружной. Какая нужна, определяет принтер: проверьте руководство к своей модели. Быстрая проверка: прижмите наклейку к внешней стороне рулона. Если осталась краска, это Ink Out.',
                    'en' => 'Ink In means the ink is on the inside of the ribbon, Ink Out on the outside. Your printer decides which one you need, so check its manual. Quick test: press a sticky label against the outside of the roll. If ink comes off, it is Ink Out.',
                ],
                'keywords' => [
                    'uz' => ['ink in', 'ink out', "bo'yoq tomoni"],
                    'ru' => ['ink in', 'ink out', 'намотка', 'сторона краски'],
                    'en' => ['ink in', 'ink out', 'ink side', 'winding'],
                ],
                'buttons' => [],
            ],
            'become-seller' => [
                'name' => 'Become a seller',
                'reply' => [
                    'uz' => "O'zbekistonlik sotuvchilar Ribbon'da mahsulot joylashtirishi mumkin. Termotransfer lentalar bepul joylashtiriladi, boshqa kategoriyalar uchun oylik obuna kerak. Ariza qoldiring: biz STIRni tekshirib, siz bilan bog'lanamiz.",
                    'ru' => 'Продавцы из Узбекистана могут размещать товары на Ribbon. Термотрансферные ленты размещаются бесплатно, для других категорий нужна ежемесячная подписка. Оставьте заявку: мы проверим ИНН и свяжемся с вами.',
                    'en' => 'Sellers from Uzbekistan can list products on Ribbon. Thermal transfer ribbons are free to list; other categories need a monthly subscription. Apply and we will check your tax ID and contact you.',
                ],
                'keywords' => [
                    'uz' => ['sotuvchi bo\'lish', 'mahsulot joylash', 'sotmoqchiman'],
                    'ru' => ['стать продавцом', 'разместить товар', 'продавать на ribbon', 'поставщик'],
                    'en' => ['become a seller', 'list products', 'sell on ribbon', 'supplier'],
                ],
                'buttons' => [
                    $this->button('url', '/sellers/register', ['uz' => 'Ariza qoldirish', 'ru' => 'Оставить заявку', 'en' => 'Apply']),
                ],
            ],
        ];
    }

    private function button(string $action, string $target, array $label): array
    {
        return ['action' => $action, 'target' => $target, 'label' => $label];
    }
}
