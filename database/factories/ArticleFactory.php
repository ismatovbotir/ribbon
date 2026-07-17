<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locales = config('ribbon.locales');
        $title = $this->faker->sentence(4);

        return [
            'type' => 'article',
            'title' => array_fill_keys($locales, $title),
            'slug' => array_fill_keys($locales, Article::generateUniqueSlug($title, $locales[0])),
            'excerpt' => array_fill_keys($locales, $this->faker->sentence()),
            'body' => array_fill_keys($locales, '<p>'.$this->faker->paragraph().'</p>'),
            'published_at' => now(),
        ];
    }
}
