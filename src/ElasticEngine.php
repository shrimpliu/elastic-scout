<?php

namespace Shrimp\ElasticScout;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Elasticsearch\Client as Elastic;

class ElasticEngine extends Engine
{
    /**
     * @var string
     */
    protected $index;

    /**
     * @var Elastic
     */
    protected $elastic;

    function __construct(Elastic $elastic, $index)
    {
        $this->elastic = $elastic;
        $this->index = $index;
    }

    // 自定义映射字段
    public function mapProperties($type, $properties)
    {
        $params = [
            'index' => $this->index,
            'type' => $type,
            'body' => [
                $type => [
                    'properties' => $properties
                ]
            ]
        ];

        try {
            $this->elastic->indices()->putMapping($params);
        } catch (Missing404Exception $exception) {
            $params = [
                'index' => $this->index,
                'body' => [
                    'mappings' => [
                        "_default_" => [
                            "_source" => [
                                "enabled" => true
                            ],
                            "dynamic_templates" => [
                                [
                                    "string" => [
                                        "match" => "*",
                                        "match_mapping_type" => "string",
                                        "mapping" => [
                                            "type" => "string",
                                            "analyzer" => "ik_smart"
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        $type => [
                            'properties' => $properties
                        ]
                    ]
                ]
            ];
            $this->elastic->indices()->create($params);
        }
    }

    public function update($models)
    {
        $chunks = $models->chunk(2000);
        $chunks->each(function ($chunk) {
           $body = [];
           $chunk->each(function ($model) use (&$body) {
               $body[] = [
                   "update" => [
                       "_index" => $this->index,
                       "_type" => $model->searchableAs(),
                       "_id" => $model->getKey(),
                   ]
               ];
               $body[] = [
                   "doc" => $model->toSearchableArray(),
                   "doc_as_upsert" => true
               ];
           });
           $this->elastic->bulk(["body" => $body]);
        });
    }

    public function delete($models)
    {
        $chunks = $models->chunk(2000);
        $chunks->each(function ($chunk) {
            $body = [];
            $chunk->each(function ($model) use (&$body) {
                $body[] = [
                    "delete" => [
                        "_index" => $this->index,
                        "_type" => $model->searchableAs(),
                        "_id" => $model->getKey(),
                    ]
                ];
            });
            $this->elastic->bulk(["body" => $body]);
        });
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        $result['nbPages'] = $result['hits']['total']/$perPage;

        return $result;
    }

    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }

            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    public function performSearch($builder, $options = [])
    {
        $bool = [];

        $keyword = empty($builder->query) ? "*" : $builder->query;
        $bool = array_merge($bool, [
            'must' => [['query_string' => [ 'query' => "{$keyword}"]]]
        ]);

        if (is_array($builder->filter) && !empty($builder->filter)) {

            $bool = array_merge($bool, [
                'filter' => $builder->filter
            ]);

        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $bool['must'] = array_merge($bool['must'], $options['numericFilters']);
        }

        $params = [
            'index' => $this->index,
            'type' => $builder->index ?: $builder->model->searchableAs(),
            'body' => [
                'query' => [
                    'bool' => $bool
                ]
            ]
        ];

        if (!empty($builder->suggestions)) {
            $params['body']['suggest'] = $builder->suggestions;
        }

        if (!empty($builder->aggs)) {
            $params['body']['aggs'] = $builder->aggs;
        }

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        return $this->elastic->search($params);
    }

    public function map($results, $model, $withTrashed =  false)
    {
        if (count($results['hits']['total']) === 0) {
            return collect();
        }

        $keys = collect($results['hits']['hits'])
            ->pluck('_id')->values()->all();

        $query = $model->whereIn(
            $model->getKeyName(), $keys
        );

        if ($withTrashed) {
            $query = $query->withTrashed();
        }

        $models = $query->get()->keyBy($model->getKeyName());

        return collect($results['hits']['hits'])->map(function ($hit) use ($model, $models) {
            return isset($models[$hit['_id']]) ? $models[$hit['_id']] : null;
        })->filter();
    }

    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }

    protected function sort($builder)
    {

        $sort = [];

        if (!empty($builder->query)) {
            $sort = [[
                "_score" => "desc"
            ]];
        }

        if (count($builder->orders) > 0) {
            foreach ($builder->orders as $order) {
                $sort[] = [$order['column'] => $order['direction']];
            }
        }

        return count($sort) > 0 ? $sort : null;
    }

}