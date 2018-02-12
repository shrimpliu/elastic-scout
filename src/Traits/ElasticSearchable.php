<?php

namespace Shrimp\ElasticScout\Traits;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Searchable;
use Shrimp\ElasticScout\ElasticBuilder as Builder;

trait ElasticSearchable
{
    use Searchable;

    public static function search($query = '', $callback = null)
    {
        return new Builder(new static, $query, $callback);
    }

    public static function suggest($field, $query = '', $callback = null)
    {
        $builder = new Builder(new static, $query, $callback);
        return $builder->suggest($field, $query);
    }

    public static function mapProperties()
    {
        $engine = app(EngineManager::class)->engine();
        $model = new static;
        $engine->mapProperties($model->searchableAs(), $model->customSearchProperties());
    }

    public static function makeAllSearchable()
    {
        $self = new static();

        $self->newQuery()
            ->orderBy($self->getKeyName())
            ->withTrashed()
            ->searchable();
    }

    public static function removeAllFromSearch()
    {
        $self = new static();

        $self->newQuery()
            ->orderBy($self->getKeyName())
            ->withTrashed()
            ->unsearchable();
    }

    public function customSearchProperties()
    {
        return [];
    }

    public function defaultFilter()
    {
        return null;
    }

}