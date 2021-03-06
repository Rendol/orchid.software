<?php

namespace App\Console\Commands;

use Composer\Semver\Semver;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Orchid\Platform\Core\Models\Post;

class PackagistPlugins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packagist:load';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Packagist Load Plugins';

    /**
     * @var Client
     */
    public $client;

    /**
     * PackagistPlugins constructor.
     */
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://packagist.org/',
            'timeout'  => 2.0,
        ]);

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->loadPage();
    }

    /**
     * @param int $page
     */
    private function loadPage(int $page = 1)
    {
        $response = $this->client->request('GET', 'search.json', [
            'query' => [
                'tags' => 'laravel', //orchid-package,
                'page' => $page,
            ],
        ]);

        $content = json_decode($response->getBody()->getContents(), true);

        foreach ($content['results'] as $package) {
            try {
                $this->loadPackage($package);
            } catch (\Exception $exception) {
                echo $exception->getMessage();
            }
        }

        if (key_exists('next', $content)) {
            $this->loadPage(($page + 1));
        }

    }

    /**
     * @param array $package
     */
    private function loadPackage(array $package)
    {
        $post = Post::firstOrNew([
            'user_id' => 1,
            'type'    => 'plugins',
            'status'  => 'publish',
            'slug'    => $package['name'],
        ]);

        $response = $this->client->request('GET', $package['url'] . ".json");
        $content = json_decode($response->getBody()->getContents(), true);

        $package = $content['package'];

        $lastVersion = $this->howVersion($package['versions']);

        $package['info'] = $package['versions'][$lastVersion];
        unset($package['versions']);


        //https://api.github.com/repos/tabuna/Example-Package/readme
        $pageDescriptions = $this->client->request('GET',
            "https://api.github.com/repos/" . $package['name'] . "/readme", [
                'query' => [
                    'client_id'     => env('GITHUB_CLIENT_ID'),
                    'client_secret' => env('afd1f167ccd0b6d2192c3377c62bcc9561935203'),
                ],
            ]);

        $pageDescriptions = json_decode($pageDescriptions->getBody()->getContents(), true);
        $package['content'] = base64_decode($pageDescriptions['content']);

        $post->content = $package;
        $post->options = [];
        $post->save();
    }


    /**
     * @param $versions
     *
     * @return mixed
     */
    private function howVersion($versions)
    {
        $versions = array_keys($versions);
        $versions = Semver::sort($versions);

        if (count($versions) > 1) {
            return $versions[count($versions) - 2];
        }

        return array_pop($versions);
    }

}
