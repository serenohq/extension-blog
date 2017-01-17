<?php namespace Sereno\Extensions;

use Sereno\AbstractExtension;

class BlogExtension extends AbstractExtension
{
    public function provide()
    {
        $this->registerConfig('blog', require __DIR__.'/config.php');
    }

    public function getBuilders(): array
    {
        return [
            Blog\BlogPostBuilder::class,
            Blog\CollectionBuilder::class,
            Blog\BlogBuilder::class,
        ];
    }

    public function getViewsDirectory(): array
    {
        return [dirname(__DIR__).'/resources/views'];
    }

    public function getContentDirectory(): array
    {
        return (array) config('blog.directory');
    }
}
