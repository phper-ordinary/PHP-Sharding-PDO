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
/**
 * 更新 sharding
 * User: lys
 * Date: 2019/8/1
 * Time: 17:03
 * @var \PhpShardingPdo\Core\ShardingPdo $this
 */
trait UpdateShardingTrait
{
    /**
     * @return bool|int
     */
    public function _updateSharding()
    {
        $sqlArr = [];
        $sql = 'update  `###TABLENAME###` set ';
        $column_str = '';
        $bindParams = [];
        if (empty($this->_incrOrDecrColumnStr)) {
            foreach ($this->_update_data as $k => $v) {
                $this->_bind_index++;
                $zwKey = ':update_' . $k . '_' . $this->_bind_index . '_0';
                $column_str .= ',' . $k . ' = ' . $zwKey;
                $bindParams[$zwKey] = $v;
            }
            !empty($column_str) && $column_str = substr($column_str, 1, strlen($column_str) - 1);
        } else {
            $column_str = $this->_incrOrDecrColumnStr;
        }
        if (empty($column_str)) {
            return false;
        }
        $sql .= $column_str . $this->_condition_str . $this->_order_str . $this->_limit_str;
        $bindParams = array_merge($bindParams, $this->_condition_bind);
        if (empty($this->_current_exec_table) && empty($this->_table_name_index)) {
            $sqlArr[] = str_replace('###TABLENAME###', $this->_table_name, $sql);
        } elseif (empty($this->_current_exec_table) && !empty($this->_table_name_index)) {
            foreach ($this->_table_name_index as $tableName) {
                $sqlArr[] = str_replace('###TABLENAME###', $tableName, $sql);
            }
        } else {
            $sqlArr[] = str_replace('###TABLENAME###', $this->_current_exec_table, $sql);
        }
        $statementArr = [];
        $rowsCount = 0;
        $searchFunc = function ($sql) use (&$statementArr, $bindParams, &$rowsCount) {
            if (!empty($this->getCurrentExecDb())) {  //有找到具体的库
                $this->setUseDatabaseArr($this->getCurrentExecDb());
                /**
                 * @var \PDOStatement $statement
                 */
                $statement = $statementArr[] = $this->getCurrentExecDb()->prepare($sql, array(\PDO::ATTR_CURSOR => $this->attr_cursor));
                $res = $statement->execute($bindParams);
                $this->_addExeSql($sql, $bindParams, $this->getCurrentExecDb());
                $rowsCount += $statement->rowCount();
                if (empty($res)) {
                    $this->_sqlErrors[] = [$this->getCurrentExecDb()->getDsn() => $statement->errorInfo()];
                }
                return $res;
            }
            /**
             * @var \Pdo $db
             */
            foreach ($this->_databasePdoInstanceMap() as $key => $db) {  //没有找到具体的库
                $this->setUseDatabaseArr($db);
                /**
                 * @var \PDOStatement $statement
                 */
                $statement = $statementArr[] = $db->prepare($sql, array(\PDO::ATTR_CURSOR => $this->attr_cursor));
                $res[$key] = $statement->execute($bindParams);
                $this->_addExeSql($sql, $bindParams, $db);
                $rowsCount += $statement->rowCount();
                if (empty($res[$key])) {
                    $this->_sqlErrors[] = [$db->getDsn() => $statement->errorInfo()];
                }
            }
            return !in_array(false, $res) ? true : false;
        };
        foreach ($sqlArr as $sql) {
            $res = $searchFunc($sql);
            if (empty($res)) {  //出现false则说有出现失败
                return false;
            }
        }
        return $rowsCount;
    }
}
