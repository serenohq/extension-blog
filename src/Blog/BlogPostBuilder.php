<?php namespace Sereno\Extensions\Blog;

use Sereno\Contracts\Builder;
use Sereno\DataExtractor;
use Sereno\ProcessorFactory;
use Symfony\Component\Finder\SplFileInfo;

class BlogPostBuilder implements Builder
{
    /**
     * List of posts.
     *
     * @var array
     */
    protected $posts = [];

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
     * Extracts front data.
     *
     * @var \Sereno\DataExtractor
     */
    protected $extractor;

    /**
     * Converts to HTML page.
     *
     * @var \Sereno\ProcessorFactory
     */
    protected $factory;

    public function __construct(DataExtractor $extractor, ProcessorFactory $factory)
    {
        $this->extractor = $extractor;
        $this->factory = $factory;
        $this->blogDirectory = config('blog.directory');
        $this->blogUrl = config('blog.url_prefix');
    }

    public function handledPatterns(): array
    {
        return [$this->blogDirectory.'/*'];
    }

    public function data(array $files, array $data) : array
    {
        $posts = $this->findPosts(array_reverse($files));

        return array_set($data, 'blog', ['posts' => array_values($posts)]);
    }

    public function build(array $files, array $data)
    {
        /** @var SplFileInfo[] $files */
        $files = array_reverse($files);
        $this->restorePostsFromData($data);
        $options = [
            'view' => [
                'extends' => config('blog.post.extends'),
                'yields'  => config('blog.post.yields'),
            ],
            'interceptor' => [$this, 'getOutputFilename'],
        ];

        foreach ($files as $file) {
            if (array_key_exists($file->getRelativePathname(), $this->posts)) {
                $this->factory->process($file, $this->prepareData($file) + $data, $options);
            }
        }
    }

    public function getOutputFilename(SplFileInfo $file)
    {
        $data = $this->posts[$file->getRelativePathname()];
        $filename = $file->getFilename();
        $basename = array_first(explode('.', $filename, 2));
        $directory = preg_replace('#^'.preg_quote($this->blogDirectory, '#').'#', '', $file->getRelativePath());
        $directory = trim($directory, DIRECTORY_SEPARATOR);

        if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})-(.*)$/', $basename, $matches)) {
            $basename = $matches[2].'-'.$matches[1];
        }

        if (hash_equals('', $directory)) {
            return trim($this->blogUrl.DIRECTORY_SEPARATOR.$basename.DIRECTORY_SEPARATOR.'index.html', DIRECTORY_SEPARATOR);
        }

        return trim($this->blogUrl.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.$basename.DIRECTORY_SEPARATOR.'index.html', DIRECTORY_SEPARATOR);
    }

    /**
     * @param SplFileInfo[] $files
     *
     * @return array
     */
    protected function findPosts(array $files): array
    {
        foreach ($files as $file) {
            if ($data = $this->extractor->get($file)) {
                $this->posts[$file->getRelativePathname()] = $this->processData($data);
                $this->posts[$file->getRelativePathname()] += [
                    'path'     => $this->getPostUrl($file),
                    'pathname' => $file->getRelativePathname(),
                    'title'    => ucfirst(str_replace('-', ' ', array_first(explode('.', $file->getFilename(), 2)))),
                ];
            }
        }

        return $this->posts;
    }

    protected function processData(array $data): array
    {
        $post = array_filter($data, function ($key) {
            return starts_with($key, 'post::');
        }, ARRAY_FILTER_USE_KEY);

        foreach ($post as $key => $val) {
            $post[str_replace('post::', '', $key)] = $val;

            unset($post[$key]);
        }
        $post += array_get($data, 'post', []);
        $post += ['__post' => $data];

        return $post;
    }

    protected function prepareData(SplFileInfo $file): array
    {
        $data = [];

        foreach ($this->posts[$file->getRelativePathname()] as $key => $value) {
            $data[$key] = json_decode(json_encode($value), false);
        }
        $data += [
            'pageTitle'       => (string) array_get($data, 'title'),
            'pageDescription' => (string) array_get($data, 'brief'),
        ];

        $filenames = array_keys($this->posts);
        $index = array_search($file->getRelativePathname(), $filenames);

        if ($index < count($index) - 1) {
            $data['prev'] = (object) $this->posts[$filenames[$index + 1]];
        }

        if ($index > 0) {
            $data['next'] = (object) $this->posts[$filenames[$index - 1]];
        }

        return $data;
    }

    protected function getPostUrl(SplFileInfo $file): string
    {
        $filename = $this->getOutputFilename($file);

        return str_replace('\\', '/', dirname($filename));
    }

    protected function restorePostsFromData(array $data)
    {
        $this->posts = array_combine(array_keys($this->posts), array_get($data, 'blog.posts'));
    }
}
