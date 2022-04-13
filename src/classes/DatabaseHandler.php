<?php
namespace Modular;

use Mpdf\Tag\P;

class DatabaseHandler {
    public static function GET_APP_CFG() {
        return json_decode(file_get_contents(getcwd() ."/config/application.json"));
    }

    public static function GET_PDO() {
        $cfg = DatabaseHandler::GET_APP_CFG();
        return new \PDO($cfg->APP_DB_TYPE . ":host=" . $cfg->APP_DB_HOST . ";dbname=" . $cfg->APP_DB_NAME, $cfg->APP_DB_USER, $cfg->APP_DB_PASS);
    }

    public static function GET_SQL_CONNECTION() {
        $cfg = DatabaseHandler::GET_APP_CFG();
        return new \mysqli($cfg->APP_DB_HOST, $cfg->APP_DB_USER, $cfg->APP_DB_PASS, $cfg->APP_DB_NAME);
    }

    public static function insertObject($obj){

        if(DatabaseHandler::tableExists($obj->tableName)) {
            try {
                $sql = "INSERT INTO ".$obj->tableName." (".$obj->generateInsertStr().") VALUES (".$obj->generateInsertVal().")";
                DatabaseHandler::GET_PDO()->exec($sql);
                return true;
            } catch (PDOException $e) {
                return false;
            }
        }
        else{
            $objName = get_class($obj);
            DatabaseHandler::createTable(new $objName());
            DatabaseHandler::insertObject($obj);
        }
    }

    public static function deleteObject ($ref) {
        try {
            // Delete Row
            $sql = "DELETE FROM {$ref->tableName} WHERE id=".$ref->id;

            // use exec() because no results are returned
            DatabaseHandler::GET_PDO()->exec($sql);

            return true;
        } catch(\PDOException $e) {
            return false;
        }
    }

    public static function updateObject ($ref) {
        try {
            // Delete Row
            $sql = $ref->generateUpdateString();

            // use exec() because no results are returned
            DatabaseHandler::GET_PDO()->exec($sql);

            return true;
        } catch(\PDOException $e) {
            return false;
        }
    }

    public static function getObject($ref, $where = array(), $opts = array(), $forceArray = false){

        $whereSQL = "";
        $isFirst = true;


        foreach ($where as $key=>$val) {
            if($isFirst) {
                $whereSQL .= " WHERE $key='$val'";
                $isFirst = false;
            }
            $whereSQL .= " AND $key='$val'";
        }

        $optsSQL = "";

        foreach ($opts as $opt) {
            $optsSQL .= "$opt ";
        }

        if(!DatabaseHandler::tableExists($ref->tableName)){
            $objName = get_class($ref);
            DatabaseHandler::createTable(new $objName());
            DatabaseHandler::getObject($ref, $where, $opts);
        }

        try{

            $sql = "SELECT * FROM ".$ref->tableName." {$whereSQL} {$optsSQL} LIMIT 1";

            $getObjs = DatabaseHandler::GET_PDO()->prepare($sql);
            $getObjs->execute();

            $objs = $getObjs->fetchAll();

            $class = get_class($ref);
            $tmp = new $class();

            if($objs != FALSE) {
                foreach ($objs[0] as $key => $value) {
                    $tmp->{$key} = $value;
                }
            }
            else{
                return false;
            }


            if($forceArray) {
                if(!is_array($tmp)) {
                    return array($tmp);
                }
            }

            return $tmp;

        }
        catch (\PDOException $e){
            echo $e->getMessage();
        }
    }

    public static function getObjects($ref, $where = array(), $opts = array()) {

        $whereSQL = "";
        $isFirst = false;

        $whereSQL = "";
        $isFirst = true;

        foreach ($where as $key=>$val) {
            if($isFirst) {
                $whereSQL .= " WHERE $key='$val'";
                $isFirst = false;
            }
            $whereSQL .= " AND $key='$val'";
        }

        $optsSQL = "";

        foreach ($opts as $opt) {
            $optsSQL .= "$opt ";
        }


        if(!DatabaseHandler::tableExists($ref->tableName)){
            $objName = get_class($ref);
            DatabaseHandler::createTable(new $objName());
            DatabaseHandler::getObject($ref, $where, $opts);
        }

        try{

            $sql = "SELECT * FROM ".$ref->tableName."{$whereSQL} {$optsSQL}";
            $getObjs = DatabaseHandler::GET_PDO()->prepare($sql);
            $getObjs->execute();

            $tmp = array();

            $objs = $getObjs->fetchAll();

            foreach($objs as $obj) {
                $class = get_class($ref);
                $tmpObj = new $class();
                foreach($obj as $key=>$value){
                    $tmpObj->{$key} = $value;
                }
                array_push($tmp, $tmpObj);
            }

            return $tmp;

        }
        catch (PDOException $e){
            echo $e->getMessage();
        }
    }

    /* Backwards-compatibility functions */
    public static function getAllObjects($refObj) {
        return DatabaseHandler::getObjects($refObj, array(), array());
    }

    public static function getInstance($refObj, $key, $value)
    {
        return DatabaseHandler::getObject($refObj, [$key => $value], []);
    }


    public static function tableExists($table) {

        // Try a select statement against the table
        // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
        try {
            $result = DatabaseHandler::GET_PDO()->query("SHOW TABLES LIKE \"{$table}\"");

            if ($result->rowCount() == 1) {
                return true;
            } else {
                return false;
            }

        } catch (\Exception $e) {
            // We got an exc exception == table not found
            echo $e->getMessage();

            return FALSE;
        }

    }

    public static function conditionalDeleteRow($objRef, $key, $value) {
        $table = $objRef->tableName;
        // Try a select statement against the table
        // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
        try {
            $result = DatabaseHandler::GET_PDO()->query("DELETE FROM {$table} WHERE `$key`='$value'");
        } catch (\Exception $e) {
            // We got an exception == table not found
            return FALSE;
        }

        // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
        return true;
    }


    public static function createTable($obj){


        if(DatabaseHandler::tableExists($obj->tableName)) {
            return true;
        }

        // Try a select statement against the table
        // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
        try {
            $result = DatabaseHandler::GET_PDO()->exec($obj->generateSQLModel());
        } catch (\Exception $e) {
            // We got an exception == table not found
            return false;
        }

        // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
        return $result !== FALSE;

    }


    /*
        Checks if a database model needs a migration performed
    */
    public static function checkForMigration($mdl): bool
    {

        $lastMigration = mpcore_migration::GetWhere(["mpTableName" => $mdl->tableName]);

        if(!$lastMigration) {
            return true;
        }

        if(strcmp($mdl->generateSQLModel(), $lastMigration->generateSQLModel())) {
            return false;
        }

        return true;

    }

}



