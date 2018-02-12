<?php

namespace Shrimp\ElasticScout\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;

class MapCommand extends Command
{

    protected $signature = 'elastic:map {model}';

    protected $description = 'Map the given model into the elasticsearch index';

    public function handle(Dispatcher $events)
    {
        $class = $this->argument('model');

        $model = new $class;

        $model::mapProperties();

        $this->info('['.$class.'] have been mapped.');
    }

}