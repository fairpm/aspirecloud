<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageRelease;
use Database\Factories\PackageReleaseFactory;
use Illuminate\Database\Seeder;

class Typo3ExtensionSeeder extends Seeder
{
    public function run(): void
    {
        if (Package::where('type', 'typo3-extension')->exists()) {
            return;
        }

        $extensions = [
            ['name' => 'News System', 'slug' => 'news', 'description' => 'Versatile news system based on Extbase and Fluid. Editor friendly, built-in support for categories, tags, and related articles.', 'tags' => ['news', 'blog', 'articles']],
            ['name' => 'Powermail', 'slug' => 'powermail', 'description' => 'All-in-one form builder for TYPO3. Create contact forms, surveys, and registration forms with drag and drop.', 'tags' => ['forms', 'contact', 'email']],
            ['name' => 'RealURL', 'slug' => 'realurl', 'description' => 'Speaking URLs for TYPO3. Transforms ugly query parameters into human-readable paths for better SEO.', 'tags' => ['seo', 'urls', 'routing']],
            ['name' => 'Solr for TYPO3', 'slug' => 'solr', 'description' => 'Apache Solr integration for TYPO3. Enterprise search with faceting, suggestions, and relevance tuning.', 'tags' => ['search', 'indexing', 'enterprise']],
            ['name' => 'Mask', 'slug' => 'mask', 'description' => 'Create custom content elements and page properties without writing a single line of code. Visual backend editor.', 'tags' => ['content', 'editor', 'backend']],
            ['name' => 'Image Gallery', 'slug' => 'gallery', 'description' => 'Responsive image gallery with lightbox support. Grid, masonry and slider layouts for media collections.', 'tags' => ['images', 'gallery', 'media']],
            ['name' => 'SEO Basics', 'slug' => 'cs-seo', 'description' => 'Essential SEO tools for TYPO3. Meta tags, Open Graph, structured data, sitemap generation, and canonical URLs.', 'tags' => ['seo', 'meta', 'sitemap']],
            ['name' => 'Flux', 'slug' => 'flux', 'description' => 'Fluid templating engine integration. Build flexible page layouts and content elements using Fluid templates.', 'tags' => ['templates', 'fluid', 'layout']],
            ['name' => 'Scheduler Tasks', 'slug' => 'scheduler', 'description' => 'Cron-like task scheduler for TYPO3. Automate recurring jobs like imports, cleanups, and notifications.', 'tags' => ['automation', 'cron', 'tasks']],
            ['name' => 'Secure Downloads', 'slug' => 'secure-downloads', 'description' => 'Protect file downloads with access control. Track downloads, restrict by user group, and log access.', 'tags' => ['security', 'files', 'downloads']],
        ];

        foreach ($extensions as $ext) {
            $tags = $ext['tags'];
            unset($ext['tags']);

            $package = Package::factory()
                ->withAuthors()
                ->withMetas()
                ->withSpecificTags($tags)
                ->typo3Extension()
                ->create($ext);

            $package->releases()->createMany(
                PackageReleaseFactory::new()
                    ->typo3()
                    ->count(2)
                    ->make(['package_id' => $package->id])
                    ->toArray(),
            );
        }
    }
}
