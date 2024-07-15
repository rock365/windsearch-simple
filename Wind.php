<?php

// +----------------------------------------------------------------------
// | WindSearch(simple) PHP全文检索中间件 WindSearch 基础版
// +----------------------------------------------------------------------
// | Licensed: Apache 2.0
// +----------------------------------------------------------------------
// | Author: rock365 1593250826@qq.com
// +----------------------------------------------------------------------
// | Version: 1.0
// +----------------------------------------------------------------------

namespace WindSearch\Core;

class Wind
{

    private $indexTempContainer = [];
    private $indexDir  = '';
    private $ngramLen = 2; //ngram分词的窗口长度，默认2个字符

    public function __construct($IndexName = 'default')
    {

        // 检测PHP环境
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            die('PHP版本需要≥5.6');
        }

        $this->IndexName = $IndexName;
        // 存放索引数据的目录 引擎所在的目录
        $this->indexDir = dirname(__FILE__) . '/windIndex/';

        if (!is_dir($this->indexDir)) {
            mkdir($this->indexDir, 0777);
        }
    }

    /**
     * 删除文件夹
     */
    private static function del_dir($dir)
    {
        if (substr($dir, -1) != '/') {
            $dir = $dir . '/';
        }
        if (!is_dir($dir)) {
            return;
        }

        $fileList = scandir($dir);

        foreach ($fileList as $file) {

            if (($file != '.') && ($file != '..')) {
                $fullpath = $dir . $file;
                if (!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    self::del_chilren_dir($fullpath);
                }
            }
        }

        rmdir($dir);
    }

    /**
     * 删除子文件夹
     */
    private static function del_chilren_dir($dir)
    {
        $fileList = scandir($dir);
        foreach ($fileList as $file) {

            if ($file != "." && $file != "..") {
                $fullpath = $dir . '/' . $file;
                if (!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    self::del_chilren_dir($fullpath);
                }
            }
        }

        rmdir($dir);
    }


    /**
     * 创建索引库文件夹
     */
    public function createIndex()
    {
        if (!is_dir($this->indexDir . $this->IndexName . '/')) {
            mkdir($this->indexDir . $this->IndexName . '/', 0777);
        }
    }

    public function delIndex()
    {
        $this->del_dir($this->indexDir . $this->IndexName . '/');
    }


    /**
     * 导入数据入口
     */
    public function indexer($id, $string)
    {
        $id = (int)$id;
        $terms = $this->analyzer($string, 2);
        if (!empty($terms)) {
            $this->indexTempContainer[$id] = $terms;
        }
    }

    /**
     * 批量写入文件
     */
    public function batchWrite()
    {
        $temp = [];
        if (!empty($this->indexTempContainer)) {
            foreach ($this->indexTempContainer as $d => $terms) {
                foreach ($terms as $term) {
                    $temp[] = $term . ' ' . $d;
                }
            }
        }

        if (!empty($temp)) {
            file_put_contents($this->indexDir . $this->IndexName . '/temp.index', implode(PHP_EOL, $temp), FILE_APPEND);
        }
    }

    /**
     * 构建索引
     */
    public function buildIndex()
    {
        $indexFile = $this->indexDir . $this->IndexName . '/temp.index';
        if (is_file($indexFile)) {
            $file = fopen($indexFile, "r");
            $container = [];
            //输出文本中所有的行，直到文件结束为止。
            while ($line = fgets($file)) { //fgets()函数从文件指针中读取一行
                $line = trim($line);
                if ($line != '') {
                    $arr = explode(' ', $line);
                    $term = $arr[0];
                    $id = $arr[1];

                    if (!isset($container[$term]) && ($id != '')) {
                        $container[$term] = $id;
                    } else {
                        $container[$term] .= ',' . $id;
                    }
                }
            }

            if (!empty($container)) {
                file_put_contents($this->indexDir . $this->IndexName . '/index', json_encode($container));
            }
        }
    }


    /**
     * ngram分词
     */
    public function segmentNgram($str, $len = 3)
    {
        $resultArr = [];
        $regx = '/([\x{4E00}-\x{9FA5}]+)|([\x{3040}-\x{309F}]+)|([\x{30A0}-\x{30FF}]+)|([\x{AC00}-\x{D7AF}]+)|([a-zA-Z0-9]+)|([\-\_\+\!\@\#\$\%\^\&\*\(\)\|\}\{\“\\”：\"\:\?\>\<\,\.\/\'\;\[\]\~\～\！\@\#\￥\%\…\&\*\（\）\—\+\|\}\{\？\》\《\·\。\，\℃\、\.\~\～\；])/u'; //中日韩 数字字母 标点符号
        $regx_zh = '(^[\x{4e00}-\x{9fa5}]+$)'; //中文

        if (preg_match_all($regx, $str,  $mat)) {
            $all = $mat[0]; //全部
            $zh = $mat[1]; //中文

            foreach ($all as $blk) {
                if (mb_strlen($blk, 'UTF-8') == 0) {
                    continue;
                }

                //允许进行分词的内容 中文
                if (preg_match('/' . $regx_zh . '/u', $blk)) {

                    // ngram 分词方法
                    $words = $this->nGram($blk, $len);
                    if (is_array($words)) {
                        $resultArr = array_merge($resultArr, $words);
                    }
                } //不允许分词的内容 数字 字母
                else if (preg_match('/[a-zA-Z0-9]+/u', $blk)) {

                    $resultArr[] = $blk;
                }
                //其它内容 日文 韩文 符号 ...
                else {
                    for ($w = 0; $w < mb_strlen($blk, 'utf-8'); ++$w) {
                        $resultArr[] = mb_substr($blk, $w, 1, 'utf-8');
                    }
                }
            }
        }

        return $resultArr;
    }

    /**
     * @param $str 待切分的字符串
     * @param $len 指定的字符串切分个数
     */
    private function nGram($str, $len = 3)
    {
        // 字符串长度
        $strLen = strlen($str);
        // 截取窗口的字符字节数
        $len = $len * 3;

        if ($strLen <= $len) {
            return [$str];
        }
        $resArr = [];
        $endPos = $strLen - $len + 1;

        for ($i = 0; $i < $endPos; $i += 3) {
            $resArr[] = substr($str, $i, $len);
        }

        return $resArr;
    }


    /**
     * 分词入口
     */
    private function analyzer($string, $len = 2)
    {
        $string = strtolower($string);
        $terms = $this->segmentNgram($string, $len);
        $terms = array_unique(array_filter($terms));
        return $terms;
    }

    /**
     * 搜索入口
     */
    public function search($query)
    {
        $string = $query['query'];
        $page = ((int)$query['page'] < 1) ? 1 : (int)$query['page'];
        $listRows = ((int)$query['list_rows'] < 1) ? 1 : (int)$query['list_rows'];

        $terms = $this->analyzer($string, $this->ngramLen);
        $temp = [];
        if (!empty($terms)) {
            if (is_file($this->indexDir . $this->IndexName . '/index')) {
                $indexArr = json_decode(file_get_contents($this->indexDir . $this->IndexName . '/index'), true);
                foreach ($terms as $t) {
                    if (isset($indexArr[$t])) {
                        $temp[] = explode(',', $indexArr[$t]);
                    }
                }
            }
        }

        $res = [];
        if (!empty($temp)) {
            $temp = array_merge(...$temp);
            $temp = array_count_values($temp);
            arsort($temp);
            $res = array_slice($temp, ($page - 1) * $listRows, $listRows, true);
        }
        return (array)$res;
    }
}
