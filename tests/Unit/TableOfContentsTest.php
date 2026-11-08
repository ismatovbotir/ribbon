<?php

namespace Tests\Unit;

use App\Support\TableOfContents;
use PHPUnit\Framework\TestCase;

class TableOfContentsTest extends TestCase
{
    public function test_every_h2_gets_a_unique_anchor_and_a_list_entry(): void
    {
        $toc = TableOfContents::build('<p>Intro</p><h2>Ink in &amp; out</h2><p>x</p><h2 class="a">Cores</h2><h2>Cores</h2><h3>Not listed</h3>');

        $this->assertSame([
            ['id' => 'ink-in-out', 'label' => 'Ink in & out'],
            ['id' => 'cores', 'label' => 'Cores'],
            ['id' => 'cores-2', 'label' => 'Cores'],
        ], $toc['items']);
        $this->assertStringContainsString('<h2 id="ink-in-out">Ink in &amp; out</h2>', $toc['html']);
        $this->assertStringContainsString('<h2 class="a" id="cores">Cores</h2>', $toc['html']);
        $this->assertStringContainsString('<h3>Not listed</h3>', $toc['html']);
    }

    public function test_an_existing_id_is_kept_and_text_without_headings_is_untouched(): void
    {
        $this->assertSame([['id' => 'keep', 'label' => 'Kept']], TableOfContents::build('<h2 id="keep">Kept</h2>')['items']);
        $this->assertSame(['html' => '<p>Plain</p>', 'items' => []], TableOfContents::build('<p>Plain</p>'));
    }
}
