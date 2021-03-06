<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Parsedown;
use Symfony\Component\DomCrawler\Crawler;

class Documentation
{
    /**
     * @var Parsedown
     */
    public $parse;

    /**
     * @var Storage
     */
    public $storage;

    /**
     * @var
     */
    public $crawler;

    /**
     * Documentation constructor.
     */
    public function __construct()
    {
        $this->parse = new Parsedown();
        $this->storage = Storage::disk('docs');
        $this->crawler = new Crawler();
    }

    /**
     * @param string $page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($page = 'index')
    {

        $lang = App::getLocale();

        $page = $this->getPage($lang, $page);

        return view('pages.docs', [
            'menu'        => $this->getMenu($lang),
            'content'     => $page['content'],
            'title'       => $page['title'],
            'description' => $page['description'],
        ]);
    }


    /**
     * @param $lang
     *
     * @return mixed
     */
    private function getMenu($lang)
    {

        return Cache::remember('menu' . $lang, 60, function () use ($lang) {
            $menu = $this->storage->get("/$lang/documentation.md");

            return $this->parse->text($menu);
        });
    }


    /**
     * @param $lang
     * @param $page
     *
     * @return mixed
     */
    private function getPage($lang, $page)
    {
        return Cache::remember('page'.$page . $lang, 60, function () use ($lang, $page) {
            $contents = $this->storage->get("/$lang/$page.md");
            $contents = $this->parse->text($contents);

            $this->crawler->addHtmlContent($contents);
            $title = $this->crawler->filter('h1')->first()->text();
            $description = $this->crawler->filter('p')->first()->text();

            return [
                'content'     => $contents,
                'title'       => $title,
                'description' => $description,
            ];
        });
    }

}
