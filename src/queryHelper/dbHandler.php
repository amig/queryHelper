<?php

namespace queryHelper;

use MysqliDb;

class dbHandler {

  /**
   *
   * @var string
   */
  private $query;

  /**
   *
   * @var string
   */
  private $where = '';

  /**
   *
   * @var string
   */
  private $sort = '';

  /**
   *
   * @var array
   */
  private $union = [];

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
   * Build the query for the selected column list with limit and offset
   *
   * @param string $tableName
   * @param string $columnList Default *
   * @param int $offset Default null
   * @param int $limit Default null
   * @return array
   * @throws \Exception
   */
  public function buildQuery($tableName, $columnList = '*', $offset = null, $limit = null) {
    $l = '';
    if (!is_null($offset)) {
      $l .= " LIMIT {$offset}";
      if ($limit) {
        $l .= ", {$limit}";
      }
    }

    $this->query = "SELECT {$columnList} FROM {$tableName}{$this->where}{$this->sort}{$l}";

    return $this;
  }

  /**
   * Adds a query to the union array and reset query, where and sort vars
   *
   * @return $this
   * @throws \Exception
   */
  public function addQueryToUnion() {
    if (empty($this->query)) {
      throw new \Exception('Cannot add an empty query to union', 500);
    }

    $this->union[] = $this->query;
    // Reset all vars
    $this->query = '';
    $this->where = '';
    $this->sort = '';

    return $this;
  }

  /**
   * Build a union query from the private $union var
   *
   * @return $this
   * @throws \Exception
   */
  public function buildUnionQuery() {
    if (empty($this->union)) {
      throw new \Exception('Cannot build a union query', 500);
    }

    $this->query = implode(' UNION ', $this->union);

    return $this;
  }

  /**
   * Run the query stored in the private $query var
   *
   * @return array
   * @throws \Exception
   */
  public function runQuery() {
    if (empty($this->query)) {
      throw new \Exception('Cannot run an empty query', 500);
    }

    try {
      $res = $this->db
          ->rawQuery($this->query);
    } catch (\Exception $ex) {
      throw new \Exception($ex->getMessage(), $ex->getCode());
    }

    return $res;
  }

  /**
   * Build and run a single query and get one (first) result
   *
   * @param string $tableName
   * @param string $columnList Default *
   * @return array
   */
  public function getOne($tableName, $columnList = '*') {
    return $this->getAll($tableName, $columnList, 0, 1);
  }

  /**
   * Build and run a single query and get all results in accordance with the limit and offset
   *
   * @param string $tableName
   * @param string $columnList Default *
   * @param int $offset Default null
   * @param int $limit Default null
   * @return array
   * @throws \Exception
   */
  public function getAll($tableName, $columnList = '*', $offset = null, $limit = null) {
    try {
      $res = $this->db
          ->buildQuery($tableName, $columnList, $offset, $limit)
          ->runQuery();
    } catch (\Exception $ex) {
      throw new \Exception($ex->getMessage(), $ex->getCode());
    }

    return $res;
  }

}
