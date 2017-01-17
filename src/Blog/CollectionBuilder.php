<?php

namespace Sereno\Extensions\Blog;

use Sereno\Contracts\Builder;
use Sereno\ProcessorFactory;

class CollectionBuilder implements Builder
{
    protected $collections = [];

    /**
     * Blog directory.
     *
     * @var string
     */
    protected $blogDirectory;

    /**
     * Blog URL prefix.
     *
     * @var string
     */
    protected $blogUrl;

    /**
     * @var \Sereno\ProcessorFactory
     */
    protected $processor;

    public function __construct(ProcessorFactory $processor)
    {
        $this->processor = $processor;

        $this->blogDirectory = config('blog.directory');
        $this->blogUrl = config('blog.url_prefix');
    }

    public function handledPatterns(): array
    {
        return [$this->blogDirectory.'/*'];
    }

    public function data(array $files, array $data) : array
    {
        $posts = (array) array_get($data, 'post.blogs');

        foreach ($posts as $post) {
            if ($this->isCollection($post)) {
                $collection = $this->getCollectionSlug($post);

                $this->addToCollection($collection, $post);
            }
        }

        foreach ((array) array_get($data, 'post.blogs') as $index => &$post) {
            $post['collection'] = $this->getCollectionOf($post);
        }

        array_set($data, 'blog.collections', $this->collections);

        return $data;
    }

    public function build(array $files, array $data)
    {
    }

    protected function isCollection($post)
    {
        return count(explode('/', $this->normalizePathname($post['pathname']))) > 1;
    }

    protected function getCollectionSlug($post)
    {
        return array_first(explode('/', $this->normalizePathname($post['pathname']), 2));
    }

    protected function addToCollection(string $collection, $post)
    {
        if (! array_key_exists($collection, $this->collections)) {
            $this->collections[$collection] = [
                'name'  => $this->deSlugify($collection),
                'path'  => $collection,
                'posts' => [],
            ];
        }

        array_unshift($this->collections[$collection]['posts'], $post);
    }

    protected function deSlugify(string $collection): string
    {
        return ucfirst(str_replace('-', ' ', $collection));
    }

    protected function getCollectionOf($post)
    {
        if ($this->isCollection($post)) {
            return $this->collections[$this->getCollectionSlug($post)];
        }
    }

    protected function normalizePathname(string $pathname): string
    {
        return trim(preg_replace('#^'.preg_quote($this->blogDirectory, '#').'#', '', $pathname), DIRECTORY_SEPARATOR);
    }
}
