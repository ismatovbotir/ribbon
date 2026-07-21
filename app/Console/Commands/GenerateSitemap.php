<?php

namespace App\Console\Commands;

use App\Services\SitemapGeneratorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Regenerates public/sitemap.xml on demand — the same
 * SitemapGeneratorService::generateAndStore() call the admin Settings
 * "regenerate sitemap" button uses, exposed here so it can also be cron'd
 * (not wired to a schedule yet — see the task this shipped under).
 */
#[Signature('sitemap:generate')]
#[Description('Regenerate public/sitemap.xml from the current catalog/articles')]
class GenerateSitemap extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SitemapGeneratorService $sitemap): int
    {
        $count = $sitemap->generateAndStore();

        $this->info("Sitemap generated: {$count} URLs written to public/sitemap.xml");

        return self::SUCCESS;
    }
}
