<?php

namespace Tests\Unit;

use App\Support\Icon;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

class IconRegistryTest extends TestCase
{
    public function test_every_registered_icon_file_exists(): void
    {
        foreach (Icon::MAP as $name => $file) {
            $this->assertFileExists(resource_path("svg/icons/{$file}.svg"), "icon [$name]");
        }
    }

    public function test_aliases_and_category_icons_resolve(): void
    {
        foreach (array_merge(array_keys(Icon::ALIASES), Icon::CATEGORY_ICONS) as $name) {
            $this->assertTrue(Icon::exists($name), "icon [$name]");
        }
    }

    public function test_svg_is_decorative_and_themeable(): void
    {
        $carbon = (string) Icon::svg('edit', 'class="size-5"');
        $this->assertStringContainsString('class="size-5"', $carbon);
        $this->assertStringContainsString('aria-hidden="true"', $carbon);
        $this->assertStringContainsString('fill="currentColor"', $carbon);

        $custom = (string) Icon::svg('ribbon-roll');
        $this->assertStringContainsString('stroke="currentColor"', $custom);
        $this->assertStringNotContainsString('fill="currentColor"', $custom);

        Exceptions::fake();
        $this->assertSame('', (string) Icon::svg('no-such-icon'));
        Exceptions::assertReported(\InvalidArgumentException::class);
    }

    /** Every literal icon name used in a Blade view must exist in the registry. */
    public function test_icon_names_used_in_views_exist(): void
    {
        $names = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            preg_match_all('/<x-(?:storefront\.)?icon\b[^>]*?\sname="([a-z0-9-]+)"/', $source, $m1);
            preg_match_all('/<x-menu\.item\b[^>]*?\sicon="([a-z0-9-]+)"/', $source, $m2);
            preg_match_all('/<x-panel\.nav-item\b[^>]*?\sicon="([a-z0-9-]+)"/', $source, $m3);
            preg_match_all("/'icon' => '([a-z0-9-]+)'/", $source, $m4);

            foreach (array_merge($m1[1], $m2[1], $m3[1], $m4[1]) as $name) {
                $names[$name][] = $file->getFilename();
            }
        }

        $this->assertNotEmpty($names);

        foreach ($names as $name => $files) {
            $this->assertTrue(Icon::exists($name), "icon [$name] used in ".implode(', ', array_unique($files)));
        }
    }
}
