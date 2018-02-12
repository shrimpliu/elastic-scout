<?php

namespace ShrimpLiu\ElasticScout;

use Illuminate\Support\Arr;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;

class ElasticBuilder extends Builder
{

    /**
     * 过滤条件
     *
     * @var array
     */
    public $filter = [];

    public $suggestions = [];

    /**
     * 聚合语句
     * @var array
     */
    public $aggs = [];

    /**
     * 是否包含已删除
     * @var bool
     */
    public $withTrashed = false;

    public function filter($filter)
    {
        $this->filter = array_merge_recursive($this->filter, $filter);
        return $this;
    }

    public function orderBy($column, $direction = 'asc')
    {
        if (is_array($column)) {

            $this->orders = $column;

        } else {
            if (is_string($direction)) {
                $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';
            }
            $this->orders[] = [
                'column' => $column,
                'direction' => $direction,
            ];
        }

        return $this;
    }

    public function suggest($field, $text)
    {
        $this->suggestions["{$field}-suggestion"] = [
            "text" => $text,
            "term" => [
                "field" => $field
            ]
        ];
        return $this;
    }

    /**
     * 包含软删除的数据
     * @return $this
     */
    public function withTrashed()
    {
        $this->withTrashed = true;
        return $this;
    }

    /**
     * 聚合
     * @param string $field
     * @param array $query
     * @return $this
     */
    public function aggs($field, $query)
    {
        $this->aggs = $query;
        $this->take(0);

        $result = $this->engine()->search($this);
        $result = $result["aggregations"];
        $result = Arr::get($result, $field);
        return $result["value"];
    }

    public function inRandomOrder()
    {
        $this->orderBy("_script", [
            "script" => "Math.random()",
            "type" => "number"
        ]);
        return $this;
    }

    public function paginate($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = Collection::make($engine->map(
            $rawResults = $engine->paginate($this, $perPage, $page), $this->model, $this->withTrashed
        ));

        $paginator = (new LengthAwarePaginator($results, $engine->getTotalCount($rawResults), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]));

        return $paginator->appends('query', $this->query)->appends('filter', $this->filter)->appends('orders', $this->orders);
    }

}