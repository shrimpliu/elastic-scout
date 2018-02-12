<?php

namespace ShrimpLiu\ElasticScout;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Laravel\Scout\EngineManager;
use ShrimpLiu\ElasticScout\Console\MapCommand;

class ElasticScoutServiceProvider extends BaseProvider
{

    public function boot()
    {

        app(EngineManager::class)->extend('elastic', function () {
            return new ElasticEngine(ClientBuilder::create()->setHosts(config('scout.elastic.hosts'))->build(), config('scout.elastic.index'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                MapCommand::class
            ]);
        }

    }

}