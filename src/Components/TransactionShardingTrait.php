<?php
/**
 * PhpShardingPdo  file.
 * @author linyushan  <1107012776@qq.com>
 * @link https://www.developzhe.com/
 * @package https://github.com/1107012776/PHP-Sharding-PDO
 * @copyright Copyright &copy; 2019-2021
 * @license https://github.com/1107012776/PHP-Sharding-PDO/blob/master/LICENSE
 */
namespace PhpShardingPdo\Components;


use PhpShardingPdo\Core\ShardingPdoContext;

/**
 * 事务管理，分库分表之后
 * User: linyushan
 * Date: 2019/8/6
 * Time: 16:22
 */
trait TransactionShardingTrait
{

    private static $_startTransCount = 'transactionSharding_startTransCount'; //事务开启统计
    private static $_useDatabaseArr = 'transactionSharding_useDatabase';  //已被使用的数据库PDO对象source,用于事务操作
    private static $_exeSqlArr = 'transactionSharding_exeSqlArr';  //事务中执行的sql
    private static $_exeSqlXaUniqidFilePath = 'transactionSharding_exeSqlXaUniqidFilePath';  //事务sql文件，用户分布式事务中错误之后的排查
    private static $_exeSqlXaUniqidFilePathArr = 'transactionSharding_exeSqlXaUniqidFilePathArr'; //真实允许中生成的xa文件路径，上面那个非

    public function initTrans(){
        ShardingPdoContext::setValue(self::$_startTransCount,0);
        ShardingPdoContext::setValue(self::$_useDatabaseArr,[]);
        ShardingPdoContext::setValue(self::$_exeSqlArr,[]);
        ShardingPdoContext::setValue(self::$_exeSqlXaUniqidFilePath,'');
   }

    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans()
    {
        ShardingPdoContext::incrValue(self::$_startTransCount);
        return;
    }

    /**
     * 提交事务
     * @access public
     * @return boolean
     */
    public function commit()
    {
        ShardingPdoContext::decrValue(self::$_startTransCount);
        if (ShardingPdoContext::getValue(self::$_startTransCount) > 0) {
            return true;
        }
        $this->_prepareSubmit(); //预提交事务
        $useDatabaseArr = ShardingPdoContext::getValue(self::$_useDatabaseArr);
        /**
         * @var \PDO $db
         */
        foreach ($useDatabaseArr as $db) {
            ShardingPdoContext::array_shift(self::$_useDatabaseArr);
//          throw new \Exception('中断则事务异常，产生xa日志');
            $db->commit();
        }
        $this->_delExeSqlLog(); //提交成功删除事务记录文件，如果没有删除成功，则说明中间存在事务提交失败
        return true;
    }

    /**
     * 事务回滚
     * @access public
     * @return boolean
     */
    public function rollback()
    {
        ShardingPdoContext::decrValue(self::$_startTransCount);
        if (ShardingPdoContext::getValue(self::$_startTransCount) > 0) {
            return true;
        }
        $useDatabaseArr = ShardingPdoContext::getValue(self::$_useDatabaseArr);
        /**
         * @var \PDO $db
         */
        foreach ($useDatabaseArr as $db) {
            ShardingPdoContext::array_shift(self::$_useDatabaseArr);
            $db->rollBack();
        }
        return true;
    }

    /**
     * 获取已被使用的数据库pdo
     */
    public static function getUseDatabaseArr()
    {
        return ShardingPdoContext::getValue(self::$_useDatabaseArr);
    }

    /**
     * 设置使用的数据库pdo
     * @return array|boolean
     * @var \PDO $db
     */
    public static function setUseDatabaseArr($db)
    {
        if (ShardingPdoContext::getValue(self::$_startTransCount) <= 0) {  //未开启事务
            return false;
        }
        if (in_array($db, self::getUseDatabaseArr())) {
            return self::getUseDatabaseArr();
        }
        $db->beginTransaction();
        ShardingPdoContext::array_push(self::$_useDatabaseArr, $db);
        return ShardingPdoContext::getValue(self::$_useDatabaseArr);
    }


    /**
     * 防止sql注入自定义方法三
     * author: xiaochuan
     * @https://www.cnblogs.com/lemon66/p/4224892.html
     * @param: mixed $value 参数值
     */
    private function _sqlAddslashes($value)
    {
        if (!get_magic_quotes_gpc()) {  //函数在php中的作用是判断解析用户提示的数据，如包括有:post、get、cookie过来的数据增加转义字符“ ”，以确保这些数据不会引起程序，特别是数据库语句因为特殊字符引起的污染而出现致命的错误
            // 进行过滤
            $value = addslashes($value);
        }
        $value = str_replace("_", "\_", $value);
        $value = str_replace("%", "\%", $value);
        $value = nl2br($value);
        $value = htmlspecialchars($value);
        return $value;
    }

    /**
     * xa事务中预提交事务
     */
    private function _prepareSubmit()
    {
        if (empty(ShardingPdoContext::getValue(self::$_exeSqlXaUniqidFilePath))) { //为空则不记录xa提交日志
            return false;
        }
        $log = 'START'.PHP_EOL;
        foreach (ShardingPdoContext::getValue(self::$_exeSqlArr) as $sql) {
            $log .= $sql;
        }
        $objHash = md5(spl_object_hash($this));  //加上这个避免串事务
        $_exeSqlXaUniqidFilePath = ShardingPdoContext::getValue(self::$_exeSqlXaUniqidFilePath);
        $filePath = str_replace('.log',ShardingPdoContext::getCid().'-'.$objHash.'-'.date('Y-m-d_H_i_s').'.log',$_exeSqlXaUniqidFilePath);
        ShardingPdoContext::setValue(self::$_exeSqlXaUniqidFilePathArr, []);  //清空旧的残留预提交
        ShardingPdoContext::array_push(self::$_exeSqlXaUniqidFilePathArr, $filePath);
        file_put_contents($filePath, $log.PHP_EOL.'END'.PHP_EOL, FILE_APPEND);
    }


    /**
     * 添加执行的sql
     */
    private function _addExeSql($sql, $bindParams)
    {
        $exeSql = $sql;
        foreach ($bindParams as $bKey => $bVal) {
            $bVal = $this->_sqlAddslashes($bVal);
            $exeSql = str_replace($bKey, "'$bVal'", $exeSql);
        }
        ShardingPdoContext::array_push(self::$_exeSqlArr,date('Y-m-d H:i:s') . ': ' . $exeSql .';'. PHP_EOL);
    }


    /**
     * 删除事务日志
     */
    private function _delExeSqlLog()
    {
        ShardingPdoContext::setValue(self::$_exeSqlArr, []);
        $_exeSqlXaUniqidFilePathArr = ShardingPdoContext::getValue(self::$_exeSqlXaUniqidFilePathArr);
        foreach ($_exeSqlXaUniqidFilePathArr as $filePath){
            @unlink($filePath);
        }
        ShardingPdoContext::setValue(self::$_exeSqlXaUniqidFilePathArr, []);
    }
}