<?php
namespace Modular;

class QueryBuilder {

    public $Query = "";
    private $HasWhere = "";
    private $ref;

    public function __construct($Operation, $Fields, $Table, $ref) {

        $this->table  = $Table;
        $this->Fields = $Fields;
        $this->ref    = $ref;
        $this->Query  = $Operation;

        if($Operation == "SELECT") {
            if (!$this->Fields) {
                $this->AppendQuery("*");
            } else {

                $count = count($Fields);

                for ($i = 0; $i < $count; $i++) {
                    $this->AppendQuery("`{$Fields[$i]}`");

                    if($i < $count - 1 ) {
                        $this->Query .= ",";
                    }
                }
            }
        }
        $this->AppendQuery(" FROM `{$Table}`");
    }

    private function AppendQuery($add) {
        $this->Query .= " $add";
    }

    public function Append($add) {
        $this->Query .= " $add";
        return $this;
    }


    public function And() {
        $this->AppendQuery("AND");
        return $this;
    }

    public function Where($key) {

        if(!$this->HasWhere) {
            $this->AppendQuery("WHERE");
            $this->HasWhere = TRUE;
        }

        $this->AppendQuery("`{$key}`");

        return $this;
    }

    public function IsEqualTo($val) {

        $this->AppendQuery("= '{$val}'");
        return $this;

    }

    public function ShowQuery() {

        return trim(preg_replace('/\s+/',' ', $this->Query));

    }

    public function exec() {

        $ref = $this->ref;

        if(!DatabaseHandler::tableExists($ref->tableName)){
            $objName = get_class($ref);
            DatabaseHandler::createTable(new $objName());
            DatabaseHandler::getObject($ref, $where, $opts);
        }

        try{

            $sql = $this->Query;
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
}