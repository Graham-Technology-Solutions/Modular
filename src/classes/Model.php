<?php
namespace Modular;

class Model {

    public $tableName = "";
    public $intID = "int(11) NOT NULL";

    public function getFields()
    {
        $fields = array();

        array_push($fields, "intID");

        foreach ($this as $u => $v) {
            switch($u) {
                case 'intID':
                case 'tableName':
                    break;
                default:
                    array_push($fields, $u);
            }
        }

        return $fields;
    }

    public function generateSQLModel()
    {
        $tableName = $this->tableName;
        $fields = $this->getFields();

        $model = "CREATE TABLE `$tableName` (";
        $count = 0;
        foreach ($fields as $f) {

            if(!isset($this->{$f})) {
                $val = "VARCHAR(255)";
            } else {
                $val = $this->{$f};
            }

            $model .= "`$f`" . " " . $val;
            if ($count < count($fields) - 1) {
                $model .= ",";
            }
            $count++;
        }
        $model .= "); ALTER TABLE `" . $this->tableName . "` ADD PRIMARY KEY (`intID`); ALTER TABLE `" . $this->tableName . "` MODIFY `intID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;";

        return $model;

    }

    public function generateInsertStr()
    {

        $fields = $this->getFields();

        $fieldsStr = "";
        $count = 0;
        foreach ($fields as $f) {
            if($f == "intID") {
                if(!MHelper::contains("int", $this->{$f})) {
                    $fieldsStr .= ",$f";
                    if ($count < count($fields) - 1) {
                        $fieldsStr .= ",";
                    }
                }
            } else {
                if ($f != "intID" && $f != "tableName") {
                    $fieldsStr .= "$f";
                    if ($count < count($fields) - 1) {
                        $fieldsStr .= ",";
                    }
                }
            }
            $count++;
        }

        return $fieldsStr;
    }

    public function generateInsertVal()
    {
        $fieldsStr = "";
        $count = 1;
        foreach ($this as $f => $v) {

            $safeVal = addslashes($v);

            if($f == "intID") {
                if(!MHelper::contains("int", $v)) {
                    $fieldsStr .= ",'{$safeVal}'";
                    if ($count < count($this->getFields())) {
                        $fieldsStr .= ",";
                    }
                }
            } else {
                if($f != "tableName") {
                    $fieldsStr .= "'{$safeVal}'";
                    if ($count < count($this->getFields())) {
                        $fieldsStr .= ",";
                    }
                }
            }
            $count++;
        }

        return $fieldsStr;
    }

    public static function ExecuteSQL($query) {

        $mod = DatabaseHandler::GET_SQL_CONNECTION();
        return $mod->query($query);

    }


    public function generateUpdateString() {

        //return json_encode($this->getFields());

        $str = "UPDATE {$this->tableName} SET ";

        $count = count($this->getFields());
        $inc = 0;

        foreach ($this->getFields() as $f) {

            $str .= "`{$f}` = '{$this->{$f}}',";

            $inc++;
        }

        $str = substr_replace($str ,"",-1);

        $str .= " WHERE intID='{$this->intID}'";

        return $str;

    }

    public function save() {
        $return = true;

        if ($this->intID != "int(11) NOT NULL") {
            $return = $return & DatabaseHandler::updateObject($this);
        } else {
            $return .= $return & DatabaseHandler::insertObject($this);
        }

        return $return;
    }

    public static function Reference() {
        $class = static::class;
        return new $class();
    }

    public static function GetWhereID($id) {

        $class = static::class;
        $obj = new $class();

        return DatabaseHandler::getInstance($obj, "id", $id);

    }

    public static function GetAll() {
        $class = static::class;
        $obj = new $class();
        return DatabaseHandler::getAllObjects($obj);
    }

    public static function TableName() {
        $class = static::class;
        $obj = new $class();
        return $obj->tableName;
    }

    public static function GetWhere($where) {
        $class = static::class;
        $obj = new $class();
        return DatabaseHandler::getObject($obj, $where, array());
    }

    public static function GetAllWhere($where) {
        $class = static::class;
        $obj = new $class();
        return DatabaseHandler::getObjects($obj, $where);
    }

    public function __set($name,$value) {
        switch ($name) {
            default:
                // we don't allow any magic properties set or overwriting our properties
        }
    }

    public static function Count() {
        $class = static::class;
        $obj = new $class();

        $sql = "SELECT count(*) FROM {$obj->tableName}";

        return \Modular\DatabaseHandler::GET_SQL_CONNECTION()->query($sql)->fetch_array()[0];

    }

    public function __get($name)
    {
        switch ($name) {
            default:
                // we don't allow any magic properties
        }
        return null;
    }

    public function __isset($name)
    {
        switch ($name) {
            default:
                return false;
        }
    }

    public static function init() {
        $class = static::class;
        $obj = new $class();
        return DatabaseHandler::createTable($obj);
    }

    public static function Get($f = FALSE) {

        $class = static::class;
        $obj = new $class();

        return new QueryBuilder("SELECT", $f, $obj->tableName, $obj);
    }

    public static function Select($query, $f = false) {
        $class = static::class;
        $obj = new $class();

        $qb = new QueryBuilder("SELECT", $f, $obj->tableName, $obj);
        $qb->Query = $query;

        return $qb->exec();

    }

    function updateDatabaseStructure () {



    }

}
