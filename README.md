这是一个轻量级的PHP全文检索类库，可用于中文内容的全文检索，基于倒排索引结构和ngram分词开发，引入即可使用。如果你的文章不多，搜索场景简单，那么这个插件对你来说非常适合。

接口包括：创建、删除索引库，导入数据、构建索引、搜索。注意，此插件不适合大量数据。


## 引入文件
```php
    require_once 'yourdirname/windsearch-simple/Wind.php';
```


## 导入数据
```php
    $tableName = 'test';//索引库名称
    $wind = new \WindSearch\Core\Wind($tableName);
    // 创建索$tableName引库
    $wind->createIndex();
    // 示例
    $id = 1; // id 主键 int类型
    $string = 'PHP是开源的服务器端脚本语言，主要适用于Web开发领域。'; // 需要索引的内容
    // 导入数据，此处可循环导入
    $wind->indexer($id, $string);
    
    // 循环导入完毕，批量写入文件
    $wind->batchWrite();
```


## 构建索引
```php
    // 数据批量写入完成，开始构建索引数据
    $wind->buildIndex();
```


## 开始搜索
```php
    // 开始搜索，返回id集合
    $query = [
        'query'=>'脚本语言',//搜索内容
        'page'=>1,//第几页
        'list_rows'=>10,//每页多少条
    ];
    // 搜索结果 $res: id=>命中个数
    $res = $wind->search($query);
    // 构造查询sql语句
    $ids = array_keys($res);
    $ids = implode(',',$ids);
    $sql = "select * from tablename where id in($ids)";
    //...
```


## 删除索引库
```php
    // 删除test索引库
    $tableName = 'test';//索引库名称
    $wind = new \WindSearch\Core\Wind($tableName);
    $wind->delIndex();
```


=======================================


weixin: azg555666

email: 1593250826@qq.com
