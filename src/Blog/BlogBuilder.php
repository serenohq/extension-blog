<?php namespace Sereno\Extensions\Blog;

use Sereno\Contracts\Builder;
use Sereno\ProcessorFactory;
use Sereno\Traits\ViewFinderTrait;
use Symfony\Component\Finder\SplFileInfo;

class BlogBuilder implements Builder
{
    use ViewFinderTrait;

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
     * Current page number.
     *
     * @var int
     */
    protected $page;

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
        return $data;
    }

    public function build(array $files, array $data)
    {
        $pages = array_chunk(array_get($data, 'blog.posts', []), config('blog.postsPerPage', 10));
        $total_pages = count($pages);

        $pinned = array_filter(array_get($data, 'blog.posts', []), function ($post) {
            return data_get($post, 'pinned') === true;
        });

        $options = [
            'interceptor' => [$this, 'getOutputFilename'],
        ];

        $file = $this->getView('blog.index');

        foreach ($pages as $index => $posts) {
            $this->page = $index + 1;
            $paginator = [
                'posts'        => $this->preparePosts($posts),
                'pinned'       => $pinned,
                'current_page' => $this->page,
                'prev_page'    => $this->page < $total_pages ? $this->page + 1 : null,
                'next_page'    => $this->page > 1 ? $this->page - 1 : null,
                'total_pages'  => $total_pages,
            ];

            if ($index === 0) {
                $this->page = null;
                $this->processor->process($file, $paginator + $data, $options);
                $this->page = 1;
            }

            $this->processor->process($file, $paginator + $data, $options);
        }
    }

    public function getOutputFilename(SplFileInfo $file)
    {
        if (is_null($this->page)) {
            return trim($this->blogUrl.DIRECTORY_SEPARATOR.'index.html', DIRECTORY_SEPARATOR);
        }

        return trim($this->blogUrl.DIRECTORY_SEPARATOR.$this->page.DIRECTORY_SEPARATOR.'index.html', DIRECTORY_SEPARATOR);
    }

    protected function preparePosts(array $posts)
    {
        return array_map(function ($post) {
            return (object) $post;
        }, $posts);
    }
}
