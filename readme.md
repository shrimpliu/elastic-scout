# Laravel ElasticSearch Scout引擎
- 支持自定义映射字段
- 支持ES `suggest`方法
- 支持随机排序
- 支持ES搜索语法
## 安装
使用composer安装包
```shell
composer require shrimp/elastic-scout
```
添加provider到`config/app.php`配置中(Laravel 5.4及以下版本需要)
```php
'providers' => [

    ...

    ShrimpLiu\ElasticScout\ElasticScoutServiceProvider::class,
]
```
## 配置
### 配置索引
默认索引与模型表名相同，也可以通过覆盖`searchableAs`方法来自定义。
```php
<?php
use ShrimpLiu\ElasticScout\Traits\ElasticSearchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use ElasticSearchable;

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'posts_index';
    }
}
```
### 配置可搜索数据
默认，索引会从模型的`toArray`方法来读取数据，可以覆盖`toSearchableArray`方法来自定义索引数据。
```php
<?php
use ShrimpLiu\ElasticScout\Traits\ElasticSearchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use ElasticSearchable;

    /**
     * 自定义索引数据
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author_id' => $this->author_id,
            'category_id' => $this->category_id,
            'author_name' => $this->author->name,
            'content' => $this->content,
            'is_publish' => (boolean)$this->is_publish,
            'insert_time' => $this->insert_time,
            'update_time' => $this->update_time
        ];
    }
    
    public function author()
    {
        return $this->belongsTo('App\Author', 'author_id');
    }
}
```
### 自定义索引字段类型
默认，同步到索引时，会根据数据自动选择字段类型，可以覆盖`customSearchProperties`方法来自定义字段类型。
```php
<?php
use ShrimpLiu\ElasticScout\Traits\ElasticSearchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use ElasticSearchable;

    /**
     * 自定义索引字段类型
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => ['type' => 'integer'],
            'author_id' => ['type' => 'integer'],
            'category_id' => ['type' => 'integer'],
            'author_name' => ['type' => 'string', 'index' => 'not_analyzed'],
            'is_publish' => ['type' => 'boolean'],
            'insert_time' => ['type' => 'date', 'format' => 'epoch_millis'],
            'update_time' => ['type' => 'date', 'format' => 'epoch_millis']
        ];
    }
}
```
> 有关ElasticSearch的字段数据类型可以参考[ES官方文档](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-types.html)
## 索引
### 字段映射
如果有自定义索引字段类型，在导入数据之前，需先映射字段到索引中，运行`map`命令：
```shell
php artisan elastic:map "App\Post"
```
### 批量导入
```shell
php artisan scout:import "App\Post"
```
### 批量删除
```shell
php artisan scout:flush "App\Post"
```
## 使用
### 搜索
```php
$posts = App\Post::search('php laravel')->get();
```
### 筛选
```php
$posts = App\Post::search('php laravel')->filter([
    'bool' => [
        'must' => [
            ['term' => ['category_id' => 233]],
            ['term' => ['is_publish' => true]]
        ],
        'must_not' => [
            ['term' => ['id' => 234]]
        ]
    ]
])->get();
```
### 随机排序
```php
$posts = App\Post::search('php laravel')->inRandomOrder()->get();
```
### suggest方法
```php
$posts = App\Post::suggest("title", "Laravel实现ES Scout驱动")->get();
```