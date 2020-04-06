<?php

namespace queryHelper;

use MysqliDb;

class dbHandler {

  private $sort = '';
  private $where = '';

  public function __construct($config) {
    $this->init($config);
  }

  private function init($config) {
    if (empty($config['host']) || empty($config['username']) || empty($config['password']) || empty($config['port']) || empty($config['db'])) {
      throw new \Exception('Bad configuration for dataBase.', 500);
    }

    $this->db = new MysqliDb($config);
  }

  /**
   * Clear sort clause and set it to an empty string
   *
   * @return $this
   */
  public function clearSort() {
    $this->sort = '';
    return $this;
  }

  /**
   * 
   * @param string $by Name of column to sort by
   * @param string $order Default ASC
   * @param bool $new Default false; If set to true, the where clause is cleared before we create the new one
   * @return $this
   */
  public function sort($by, $order = 'ASC', $new = false) {
    if ($new) {
      $this->clearSort();
    }
    $this->sort .= (strpos($this->sort, 'ORDER BY') === false) ? " ORDER BY {$by} {$order}" : ", {$by} {$order}";
    return $this;
  }

  /**
   * Clear the where clause and set it to empty string
   *
   * @return $this
   */
  public function clearWhere() {
    $this->where = '';
    return $this;
  }

  /**
   * Add a where clause to the query
   *
   * @param string $field
   * @param mixed $value
   * @param string $operand Default: =
   * @param string $logic Default AND
   * @param string $valueType Default int; Can be: string, date, datetime, int
   * @param bool $new Default false; If set to true, the where clause is cleared before we create the new one
   * @return $this
   */
  public function where($field, $value, $operand = '=', $logic = 'AND', $valueType = 'int', $new = false) {
    if ($new) {
      $this->clearWhere();
    }

    switch ($valueType) {
      case 'string':
        $val = "'{$value}'";
        $f = $field;
        break;
      case 'date':
        $val = "'{$value}'";
        $f = "DATE_FORMAT({$field}, '%Y-%m-%d')";
        break;
      case 'datetime':
        $val = "'{$value}'";
        $f = "DATE_FORMAT({$field}, '%Y-%m-%dT%TZ')";
        break;
      case 'int':
      default:
        $val = $value;
        $f = $field;
        break;
    }
    $v = (strtolower($operand) == 'in' || strtolower($operand) == 'not in') ? '("' . implode('","', $value) . '")' : $val;
    $this->where = (empty($this->where)) ? " WHERE {$f} {$operand} {$v}" : $this->where . " {$logic} {$f} {$operand} {$v}";
    return $this;
  }

  /**
   * Run the query and get one (first) result
   *
   * @param string $tableName
   * @param string $columnList Default *
   * @return Array
   */
  public function getOne($tableName, $columnList = '*') {
    return $this->getAll($tableName, $columnList, 0, 1);
  }

  /**
   * Run the query and get all results in accordance with the limit and offset
   *
   * @param string $tableName
   * @param string $columnList Default *
   * @param int $offset Default null
   * @param int $limit Default null
   * @return array
   * @throws \Exception
   */
  public function getAll($tableName, $columnList = '*', $offset = null, $limit = null) {
    $l = '';
    if (!is_null($offset)) {
      $l .= " LIMIT {$offset}";
      if ($limit) {
        $l .= ", {$limit}";
      }
    }

    try {
      $res = $this->db
          ->rawQuery("SELECT {$columnList} FROM {$tableName}{$this->where}{$this->sort}{$l}");
    } catch (\Exception $ex) {
      throw new \Exception($ex->getMessage(), $ex->getCode());
    }

    return $res;
  }

}
