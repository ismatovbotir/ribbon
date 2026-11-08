<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The complete ribbon guide, linked from the Ribbons category menu as its
 * buying guide. DRAFT: only the Russian text is written, for the owner to
 * review; uz and en repeat it until the style is approved.
 */
class RibbonGuideSeeder extends Seeder
{
    public function run(): void
    {
        $text = fn (string $ru) => ['uz' => $ru, 'ru' => $ru, 'en' => $ru];

        $article = Article::updateOrCreate(
            ['slug->en' => 'thermal-transfer-ribbon-guide'],
            [
                'type' => 'article',
                'title' => $text('Термотрансферные риббоны: полное руководство по выбору'),
                'slug' => ['uz' => 'termotransfer-ribbon-qollanma', 'ru' => 'termotransfernye-ribbony-rukovodstvo', 'en' => 'thermal-transfer-ribbon-guide'],
                'excerpt' => $text('Из чего состоит риббон, чем отличаются wax, wax-resin и resin, что такое Ink In и Ink Out, втулки, TTO и какие ещё бывают ленты.'),
                'short_answer' => $text('Сначала определите материал этикетки и условия её жизни: они задают состав риббона. Обычно wax подходит для бумаги и короткого срока, wax-resin для бумаги и складской логистики, resin для синтетики и тяжёлых условий. Затем проверьте по руководству принтера намотку (Ink In или Ink Out), внутренний диаметр втулки и тип головки. Риббон должен быть шире этикетки. Новый риббон всегда сначала проверяйте на своей этикетке и в своих условиях.'),
                'faq' => ['uz' => $this->faq(), 'ru' => $this->faq(), 'en' => $this->faq()],
                'body' => $text($this->body()),
                'published_at' => now()->subDay(),
                'created_by' => User::where('email', config('ribbon.super_admin.email'))->value('id'),
            ],
        );

        $category = Category::where('slug->en', 'thermal-transfer-ribbons')->first();

        if ($category) {
            $category->update(['guide_article_id' => $article->id]);
            $article->categories()->syncWithoutDetaching([$category->id]);
        }
    }

    /**
     * @return array<int, array{q: string, a: string}>
     */
    private function faq(): array
    {
        return [
            ['q' => 'Какой риббон выбрать для обычных бумажных этикеток?', 'a' => 'Чаще всего wax, если этикетка живёт недолго и её мало трут. Если этикетка проходит склад и перевозку, обычно берут wax-resin. Окончательно решает проверка на вашей этикетке.'],
            ['q' => 'Почему на полипропиленовой этикетке печать стирается?', 'a' => 'Частая причина в том, что wax плохо держится на синтетике, и настройка нагрева этого не исправит. Для синтетических этикеток обычно нужен resin. Подбирайте риббон под конкретный материал.'],
            ['q' => 'Как узнать, Ink In у меня или Ink Out?', 'a' => 'Прижмите наклейку к внешней стороне рулона. Если на ней осталась краска, это Ink Out. Если нет, это Ink In. Какая намотка нужна именно вашему принтеру, указано в его руководстве.'],
            ['q' => 'Можно ли использовать риббон уже этикетки?', 'a' => 'Не стоит. Zebra рекомендует всегда использовать риббон шире этикетки: он защищает термоголовку от износа. Проверьте также максимальную ширину риббона для вашего принтера.'],
            ['q' => 'Подойдёт ли обычный риббон для печати на пластиковых картах?', 'a' => 'Нет. Для карточных принтеров выпускают отдельные ленты с панелями, например YMCKO. Это другой класс расходных материалов, он подбирается под конкретную модель принтера.'],
        ];
    }

    private function body(): string
    {
        $figure = fn (string $file, string $alt) => '<figure><img src="/images/guides/'.$file.'" alt="'.$alt.'" width="720" loading="lazy"></figure>';

        return implode('', [
            '<h2>Что такое риббон и как он печатает?</h2>',
            '<p>Риббон, или термотрансферная лента, это тонкая плёнка с <a href="https://racklify.com/encyclopedia/what-is-a-thermal-transfer-ribbon-technical-guide/">краской на одной стороне и защитным покрытием на другой</a>. Термоголовка нагревает нужные точки, краска плавится и переходит на этикетку. Поэтому отпечаток держится намного дольше, чем у прямой термопечати, где темнеет сама этикетка.</p>',
            $figure('ribbon-layers-ru.svg', 'Слои риббона: задний слой, плёнка PET и слой краски, который под нагревом переходит на этикетку'),

            '<h2>Чем отличаются wax, wax-resin и resin?</h2>',
            '<p>Это три основных состава краски. Главный вопрос здесь не цена рулона, а <a href="https://www.chlabels.com/news/thermal-transfer-ribbon-wax-vs-resin-comparison.html">сочетание риббона и материала этикетки</a>. Неправильная пара даёт бледные штрих-коды, размазывание и лишний износ головки.</p>',
            '<table><thead><tr><th>Состав</th><th>Обычная этикетка</th><th>Стойкость</th><th>Типичное применение</th></tr></thead><tbody>',
            '<tr><td><strong>Wax</strong></td><td>Бумага</td><td>Размазывается при истирании</td><td>Этикетки на короткий срок, транспортные</td></tr>',
            '<tr><td><strong>Wax-resin</strong></td><td>Бумага с покрытием, часть синтетики</td><td>Выдерживает лёгкое истирание</td><td>Склад, логистика, розница</td></tr>',
            '<tr><td><strong>Resin</strong></td><td>Синтетика (полипропилен, полиэстер)</td><td>Лучше других держит растворители, масла и чистящие средства</td><td>Химия, производство, улица, долгий срок</td></tr>',
            '</tbody></table>',
            '<p>Это <a href="https://www.durafastlabel.com/solutions/guide-to-wax-wax-resin-resin-ribbons">общие ориентиры</a>, а составы у производителей различаются. Поэтому <a href="https://www.adazonusa.com/blog/barcode-label-information/wax-vs-wax-resin-vs-resin-ribbon">проверяйте риббон на своей этикетке и своём принтере</a> до закупки большой партии.</p>',

            '<h2>Что такое Ink In и Ink Out и зачем нужны оба?</h2>',
            '<p>Это одно и то же по сути, разница <a href="https://www.idprt.com/Blog/Label-Ribbon-CSI-vs-CSO-What-Does-That-Mean.html">только в направлении намотки</a>: краска смотрит внутрь рулона (Ink In, CSI) или наружу (Ink Out, CSO). Краска всегда должна быть обращена к этикетке, а путь ленты в разных принтерах разный. Поэтому <a href="https://www.onlinelabels.com/articles/Selecting_Thermal_Transfer_Ribbon.htm">механизм принтера определяет, какая намотка нужна</a>, и большинство моделей работает только с одной.</p>',
            $figure('ribbon-ink-in-out-ru.svg', 'Схема рулонов Ink In и Ink Out: краска к центру рулона или снаружи'),
            '<p><strong>Быстрая проверка.</strong> Прижмите наклейку к внешней стороне рулона. <a href="https://www.onlinelabels.com/articles/Selecting_Thermal_Transfer_Ribbon.htm">Осталась краска, значит Ink Out; не осталась, значит Ink In</a>. Принтеры Zebra <a href="https://www.onlinelabels.com/articles/Selecting_Thermal_Transfer_Ribbon.htm">обычно используют Ink Out, а Honeywell и Datamax обычно Ink In</a>, но это лишь общее правило: смотрите руководство своей модели.</p>',

            '<h2>Какие размеры риббона важны?</h2>',
            '<p><strong>Ширина.</strong> Zebra рекомендует <a href="https://docs.zebra.com/us/en/printers/desktop/zd611t-thermal-transfer-printer/c-mlk-ug-setup/r-mlk-ug-setup-overview/c-mlk-tt-ug-load-ribbon-01.html">всегда брать риббон шире этикетки</a>, чтобы защитить термоголовку от износа.</p>',
            '<p><strong>Внутренний диаметр втулки.</strong> Настольным принтерам <a href="https://www.barcodesinc.com/zebra/gc420-ribbons.htm">обычно нужна втулка 0,5 дюйма</a>, а промышленным <a href="https://www.barcodesinc.com/zebra/140xi4-ribbons.htm">1 дюйм</a>, хотя граница не абсолютная. Проверяйте по модели.</p>',
            '<p><strong>Ширина втулки.</strong> Она бывает больше ширины самой ленты, например лента 60 мм на втулке 110 мм. Убедитесь, что такая втулка подходит держателю вашего принтера.</p>',
            $figure('ribbon-core-ru.svg', 'Ширина ленты, ширина втулки и внутренний диаметр втулки на схеме'),
            '<p><strong>Длина.</strong> В каталогах часто встречаются <a href="https://www.barcodesinc.com/zebra/gc420-ribbons.htm">74 м для настольных принтеров</a> и <a href="https://www.barcodesinc.com/zebra/140xi4-ribbons.htm">300 и 450 м для промышленных</a>. Длина влияет на то, как часто менять рулон, поэтому смотрите максимум для своего принтера.</p>',

            '<h2>Что такое near-edge и flat head?</h2>',
            '<p>Это две конструкции термоголовки. У flat head <a href="https://www.hprt.com/blog/Flat-Head-Vs-Near-Edge-Printhead.html">нагревательные элементы в середине, а у near-edge ближе к заднему краю</a>. Для flat head подходят все три состава, а near-edge <a href="https://www.hprt.com/blog/Flat-Head-Vs-Near-Edge-Printhead.html">ограничивает выбор составами wax-resin и resin</a>. Риббон для одной конструкции не всегда работает на другой, поэтому проверяйте совместимость.</p>',
            $figure('ribbon-printhead-ru.svg', 'Сравнение зоны нагрева у плоской головки и головки near-edge'),

            '<h2>Что такое TTO?</h2>',
            '<p>TTO, термотрансферная надпечатка, это отдельное оборудование для производственных линий. Оно <a href="https://www.wolfbarco.com/node/29">печатает прямо на гибкой упаковочной плёнке и этикетках</a>, например срок годности и номер партии на пакетах с продуктами. Ленты для TTO <a href="https://www.wolfbarco.com/node/29">бывают wax-resin или resin</a>, а головка в таких системах <a href="https://assets.imp-ttr.com/s3fs-public/Thermal_Transfer_Overprint_TTO.pdf">обычно near-edge</a>. Поэтому риббон для настольного этикеточного принтера не заменяет TTO-ленту.</p>',

            '<h2>Какие ещё бывают ленты?</h2>',
            '<ul>',
            '<li><strong>Текстильные (washcare).</strong> <a href="https://www.itweb.co.za/office/kemtekimagingsystems/content/mQwkoq6Y9ZdM3r9A">Для бирок на одежду</a>.</li>',
            '<li><strong>Цветные и белые.</strong> <a href="https://www.levata.com/armor-t81829io-label-ribbon">Например, белый resin</a>, для цветной маркировки и тёмных этикеток.</li>',
            '<li><strong>Стойкие к растворителям и для контакта с пищевой упаковкой.</strong> <a href="https://www.etilux.com/en-us/labeling-marking/ribbons/thermotransfer-compatible/number%20of%20ribbons%20-3=%20box=1,20/">Производители заявляют такие свойства отдельно</a>. Спрашивайте у продавца подтверждающие документы.</li>',
            '<li><strong>Фольга для горячего тиснения.</strong> <a href="https://www.itweb.co.za/office/kemtekimagingsystems/content/mQwkoq6Y9ZdM3r9A">Другой процесс</a>, нужно своё оборудование.</li>',
            '<li><strong>Ленты для карточных принтеров.</strong> Панельные ленты для пластиковых карт: <a href="https://www.alphacard.com/blog/whats-the-difference-between-ymc-ymck-and-ymcko-ribbons/">YMCK и YMCKO</a> для цвета, <a href="https://www.idwholesaler.com/learning-center/types-id-card-printer-ribbons">K и KO</a> для монохрома. Это отдельная категория, она не взаимозаменяема с риббонами для этикеток.</li>',
            '</ul>',

            '<h2>Как выбрать риббон за 5 шагов?</h2>',
            '<ol>',
            '<li>Определите материал этикетки: бумага, бумага с покрытием или синтетика.</li>',
            '<li>Опишите условия: срок службы, трение, влага, химия, тепло, улица.</li>',
            '<li>Выберите состав: wax, wax-resin или resin.</li>',
            '<li>Проверьте по руководству принтера намотку, втулку, тип головки, максимальные ширину и длину.</li>',
            '<li>Напечатайте пробу и проверьте её в реальных условиях и сканером. <a href="https://www.adazonusa.com/blog/barcode-label-information/wax-vs-wax-resin-vs-resin-ribbon">Так советуют делать до закупки партии</a>.</li>',
            '</ol>',
        ]);
    }
}
