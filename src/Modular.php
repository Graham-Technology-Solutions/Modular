<?php

namespace Modular;

/* Set the default timezone to CST */
date_default_timezone_set('America/Chicago');

$moduleFolder = "";

if (file_exists(getcwd() . "/config/application.json")) {
    $cfg = json_decode(file_get_contents(getcwd()  . "/../application.json"));

    if (is_string($cfg->APP_MODULE_DIR)) {
        $moduleFolder = $cfg->APP_MODULE_DIR;
    } else {
        echo "APPLICATION FAILED TO START; INVALID MODULE DIRECTORY";
        exit;
    }
}

$modDir = getcwd() . "/src/" . $moduleFolder;

//Scan the modules directory for potential modules
$modules = array_diff(scandir($modDir), array('.', '..'));

//Create an array to store the loaded modules
$mods = array();

//Loop through the potential modules
foreach ($modules as $mod) {
    //Check if a configuration file exists for the module
    if (file_exists("$modDir/$mod/$mod.json")) {

        //If so, decode the configuration
        $moduleInfo = json_decode(file_get_contents("$modDir/$mod/$mod.json"));


        //Then, import the main class for that module
        require("$modDir/$mod/" . $moduleInfo->MOD_FILE);

        //Also, add the module information to the loaded module array
        $mods[$mod] = $moduleInfo;

        //Next, require the database modules if any exist for the given module
        if (isset($moduleInfo->MOD_HAS_MODELS) && $moduleInfo->MOD_HAS_MODELS == true && isset($moduleInfo->MOD_MODEL_DIR)) {
            foreach ((array_diff(scandir("$modDir/$mod/{$moduleInfo->MOD_MODEL_DIR}"), array('.', '..'))) as $dbmodel) {
                require("$modDir/$mod/{$moduleInfo->MOD_MODEL_DIR}/$dbmodel");
            }
        }

    }
}


/*
 *
 * Modular Base Class
 * Contains the base code for ModularPHP
 *
 */
class Modular {

    //Application Configuration (defaults, loaded from application.json if exists)
    private $APP_DB_TYPE    = "mysql";
    private $APP_DB_HOST    = "localhost";
    private $APP_DB_NAME    = "mpdev";
    private $APP_DB_USER    = "root";
    private $APP_DB_PASS    = "";
    private $APP_MODULE_DIR = "";
    public $APP_NAME       = "";
    public $APP_BASE_URL   = "";
    public $APP_VER = "0.0.1";
    public $APP_ORG = "";
    public $APP_CURRENT_PAGE;

    //Public Variables
    public $PDO;
    public $MYSQL;
    public $Modules = array();
    public $loadedModNames = array();
    public $Routes = array();
    public $MP_DIR;
    public $MOD_DIR;
    public $Config;

    public function __construct()
    {
        //Load the application configuration
        $this->loadConfig();

        //DatabaseHandler::createTable(new AppState_Storage());

        //Initiate a connection to the database using the provided information
        $this->PDO = new \PDO($this->APP_DB_TYPE . ":host=" . $this->APP_DB_HOST . ";dbname=" . $this->APP_DB_NAME, $this->APP_DB_USER, $this->APP_DB_PASS);

        if($this->APP_DB_TYPE == "mysql") {
            $this->MYSQL = new \mysqli($this->APP_DB_HOST, $this->APP_DB_USER, $this->APP_DB_PASS, $this->APP_DB_NAME);
        }

        //Load all of the modules
        $this->loadModules();

        $this->MP_DIR = __DIR__; //dirname(__FILE__);
        $this->MOD_DIR = getcwd()  . "/src/" . $this->APP_MODULE_DIR;

        $this->Modules = array_merge($this->Modules, array("_ModularPHP" => $this));

    }

    public function setCurrentPage($p) {
        $this->APP_CURRENT_PAGE = $p;
    }

    private function loadConfig() {
        $filePath = getcwd() . "/config/application.json";
        if(file_exists($filePath)) {
            $cfg = json_decode(file_get_contents($filePath));
            $this->Config = $cfg;
            foreach ($cfg as $opt=>$val) {
                $this->{$opt} = $val;
            }
            return true;
        }
        else {
            return false;
        }
    }

    private function loadModules() {
        global $mods;

        foreach ($mods as $modName=>$modInfo) {

            $mainClass = $modInfo->MOD_CLASS;

            if (isset($modInfo->MOD_DEPENDENCIES)) {
                foreach ($modInfo->MOD_DEPENDENCIES as $depMod) {
                    $this->loadModule($depMod);
                }
            }

            $this->loadModule($modName);
        }
    }

    private function loadModule($modName) {

        global $mods;

        if(!in_array($modName, $this->loadedModNames)) {

            $modInfo = $mods[$modName];

            $mainClass = $modInfo->MOD_CLASS;

            if (isset($modInfo->MOD_DEPENDENCIES)) {
                foreach ($modInfo->MOD_DEPENDENCIES as $depMod) {
                    $this->loadModule($depMod);
                }
            }

            if (isset($modInfo->IsLibrary) && $modInfo->IsLibrary == true) {
                ${$modName} = new $mainClass();
            } else {
                ${$modName} = new $mainClass($this, $modInfo);
            }


            if ($modInfo->MOD_HAS_ROUTES) {
                $routeDefinitionVar = $modInfo->MOD_ROUTES_VAR;

                $routes = ${$modName}->{$routeDefinitionVar};

                foreach ($routes as $rt=>$inf) {
                    $routes[$rt]["mod"] = $modName;
                }

                $this->Routes = array_merge($this->Routes, $routes);
            }

            if (isset($modInfo->MOD_HAS_MODELS) && $modInfo->MOD_HAS_MODELS == true) {
                $funcName = $modInfo->MOD_MODEL_INIT;
                ${$modName}->$funcName();
            }

            $this->Modules = array_merge($this->Modules, array("_$mainClass" => ${$modName}));
            array_push($this->loadedModNames, $modName);
        }
    }

    public function render() {

        if (isset($_GET['rt'])) {
            $route = $_GET['rt'];
        } else {
            $route = "/";
        }


        if($route != "/") {
            $rt = explode("/", $route);
            $rt = array_filter($rt);

            if(!isset($this->Routes["/" . $rt[1]]) && !isset($this->Routes[$route])){
                //Display error page if route is not found
                /* Set the app to be in an 404 Error State */
                $this->loadComponent(["component" => "Error"], false, ["error" => "404"]);
                exit;
            }


            $thisRoute = $this->Routes[$route];

        }
        else{
            $rt = array("", "/");
            $thisRoute = $this->Routes["/"];
        }

        $modDir = $this->Modules["_".$thisRoute["mod"]]->Config->MOD_TEMPLATE_DIR;

        if ($this->isRouteDynamic($rt[1])) {

            if (count($rt) > 1) {

                /* Filter through the URL request and put the variable values into an array*/
                $vars = array();
                $rtVars = array();

                for ($i = 1; $i < count($rt); $i++) {
                    array_push($vars, $rt[$i + 1]);
                }

                /* Filter through the route path to find the variable names, and put them in an array */
                $varNames = explode("/", $thisRoute["path"]);
                $varNames = array_filter($varNames);
                unset($varNames[1]);
                $varNames = array_values($varNames);

                /* Assign each match up the variable names and values and create the variables */
                for ($i = 0; $i < count($varNames); $i++) {
                    $varName = preg_replace('/{(.*?)}/', '$1', $varNames[$i]);
                    @${$varName} = $vars[$i];
                    $rtVars[$varName] = $vars[$i];
                }


                if(isset($thisRoute["component"])) {
                    $this->loadComponent($thisRoute, $rtVars);
                } else {
                    if(MPHelper::contains(".twig", $thisRoute["template"])) {
                        $loader = new \Twig\Loader\FilesystemLoader(getcwd()  . "/src/" . $this->APP_MODULE_DIR . "/" . $thisRoute["mod"] . "/" . $modDir);
                        $twig = new \Twig\Environment($loader, [
                            'cache' => getcwd() .'/var/cache',
                        ]);

                        echo $twig->render('index.html', $this->Modules);

                    } else {
                        include(getcwd() . "/src/" . $this->APP_MODULE_DIR . "/" . $thisRoute["template"]);
                    }
                }
            }
        }
        else{

            if(isset($thisRoute["redirect"])) {
                header("Location: ".$thisRoute["redirect"]);
                exit;
            }


            if(isset($thisRoute["component"])) {
                $this->loadComponent($thisRoute);
            } else {
                if(\Modular\MHelper::contains(".twig", $thisRoute["template"])) {
                    $loader = new \Twig\Loader\FilesystemLoader(getcwd() . "/src/" . $this->APP_MODULE_DIR . "/" . $thisRoute["mod"] . "/". $modDir);
                    $twig = new \Twig\Environment($loader, [
                        'cache' => getcwd() .'/var/cache',
                    ]);

                    echo $twig->render($thisRoute["template"], $this->Modules);

                } else {
                    include(getcwd()  . "/src/" . $this->APP_MODULE_DIR . "/" . $thisRoute["template"]);
                }

            }
        }

    }

    public function loadComponent($thisRoute, $routeVars = false, $passedData = false) {

        $name = $thisRoute["component"]."Controller";
        $tmp = new $name($this);
        $tmp->routeVars = $routeVars;
        $tmp->passedData = $passedData;

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tmp->__POST($_POST);
        } else if($_SERVER['REQUEST_METHOD'] === 'GET') {
            $tmp->__GET($_GET);
        }

    }
    /**
     * Tests if a route is passing data to the requested page
     * @param string $rt
     * @return bool
     */
    private function isRouteDynamic($rt){

        if (isset($this->Routes["/$rt"])){
            $thisRt = $this->Routes["/$rt"];
            if(strstr ($thisRt["path"], "{")){
                return true;
            }
            else{
                return false;
            }
        }
        else{
            return false;
        }
    }

    /**
     * Checks if a module has been loaded
     * @param string $module This is name of a module
     * @return bool
     */
    public function hasMod($module){
        if(in_array($module, $this->loadedModNames)) {
            return true;
        }
        else {
            return false;
        }
    }

    public static function GetModule($ref, $mod) {
        return $ref->Modules["_".$mod];
    }
}