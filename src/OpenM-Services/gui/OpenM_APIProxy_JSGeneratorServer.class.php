<?php

Import::php("util.OpenM_Log");

if (!Import::php("Smarty"))
    throw new ImportException("Smarty");

/**
 * 
 * @package OpenM-Services
 * @subpackage gui
 * @license http://www.apache.org/licenses/LICENSE-2.0 Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * @link http://www.open-miage.org
 * @author Gael Saunier
 */
class OpenM_APIProxy_JSGeneratorServer {

    const FILE_URL_PARAMETER = "api_gen";
    const MIN_MODE_PARAMETER = "min";
    const FILE_URL_SEPARATOR_PARAMETER = ";";

    private $root_path;

    private static function min($string) {
        $string = str_replace("= ", "=", $string);
        $string = str_replace(" =", "=", $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = str_replace("  ", " ", $string);
        $string = str_replace("\r\n", "", $string);
        $string = str_replace("\n", "", $string);
        $string = str_replace(' (', "(", $string);
        $string = str_replace('( ', "(", $string);
        $string = str_replace(' )', ")", $string);
        $string = str_replace(') ', ")", $string);
        $string = str_replace(': ', ":", $string);
        $string = str_replace(' :', ":", $string);
        $string = str_replace('{ ', "{", $string);
        $string = str_replace(' {', "{", $string);
        $string = str_replace(' }', "}", $string);
        $string = str_replace('} ', "}", $string);
        $string = str_replace(' ;', ";", $string);
        $string = str_replace('; ', ";", $string);
        $string = str_replace(' +', "+", $string);
        $string = str_replace('+ ', "+", $string);
        $string = str_replace(' -', "-", $string);
        $string = str_replace('- ', "-", $string);
        $string = str_replace(' ,', ",", $string);
        $string = str_replace(', ', ",", $string);
        return $string;
    }

    public static function display($apis, $min = true, $root_path = null) {
        $files = explode(self::FILE_URL_SEPARATOR_PARAMETER, $apis);
        header('Content-type: text/javascript');
        $smarty = new Smarty();
        $smarty->caching = true;
        $smarty->compile_check = false;
        $smarty->assign("min", $min);

        $sso_proxy = Import::getAbsolutePath("OpenM-SSO/gui/js/OpenM_SSOConnectionProxy.js");
        $api_proxy = Import::getAbsolutePath("OpenM-Services/gui/js/OpenM_APIProxy_AJAXController.js");
        $tpl = Import::getAbsolutePath("OpenM-Services/gui/tpl/OpenM_APIProxy_Controller.tpl");
        $id = "OpenM-SSO/gui/OpenM_SSOConnectionProxy.js_OpenM-Services/gui/js/OpenM_APIProxy_AJAXController.js"
                . "_" . filectime($sso_proxy) . "_"
                . filectime($api_proxy) . "_"
                . ($min ? "min" : "");
        if ($smarty->isCached($tpl, $id))
            $smarty->display($tpl, $id);
        else {
            $string = file_get_contents($sso_proxy);
            if ($min)
                $string = self::min($string);
            $smarty->assign("OpenM_SSOConnectionProxy", $string);
            $string = file_get_contents($api_proxy);
            if ($min)
                $string = self::min($string);
            $smarty->assign("OpenM_APIProxy_AJAXController", $string);
            $smarty->cache_id = $id;
            $smarty->display($tpl);
        }

        $display = __DIR__ . "/tpl/OpenM_APIProxy_JSGeneratorServer.tpl";

        foreach ($files as $api) {
            if (!is_file("$api.interface.php"))
                die("Forbidden display");

            if (!Import::php("$api"))
                throw new ImportException("$api");

            $reflexion = new ReflectionClass("$api");
            $file = $reflexion->getFileName();

            $id = $file . filectime($file) . "_" . ($min ? "min" : "");
            if ($smarty->isCached($display, $id))
                $smarty->display($display, $id);

            else {
                $smarty->cache_id = $id;
                $constants = $reflexion->getConstants();
                $arrayConstants = array();
                foreach ($constants as $name => $value) {
                    $arrayConstant = array();
                    $arrayConstant["name"] = $name;
                    $arrayConstant["value"] = $value;
                    $arrayConstants[] = $arrayConstant;
                }

                $smarty->assign("constants", $arrayConstants);
                $methods = get_class_methods("$api");
                $arrayMethods = array();

                foreach ($methods as $method) {

                    $arrayMethod = array();
                    $arrayMethod["name"] = $method;

                    $r = new ReflectionMethod($api, $method);
                    $r->getParameters();
                    $i = 1;
                    $args = $r->getParameters();

                    $arrayParameters = array();

                    foreach ($args as $param) {
                        $arrayParameter = array();
                        $arrayParameter["name"] = $param->getName();
                        $arrayParameter["isOptional"] = $param->isOptional();
                        if ($param->isOptional())
                            $arrayParameter["defaultValue"] = $param->getDefaultValue();
                        $arrayParameter["parameterName"] = "arg$i";
                        $arrayParameters["arg$i"] = $arrayParameter;
                        $i++;
                    }

                    $arrayMethod["args"] = $arrayParameters;
                    $arrayMethods[] = $arrayMethod;
                }

                $smarty->assign("methods", $arrayMethods);
                $smarty->assign("api", "$api");
                $smarty->assign("api_url", $root_path);
                $smarty->display($display);
            }
        }
    }

    public function __construct($root_path = null) {
        $this->root_path = $root_path;
    }

    public function handle() {
        if (isset($_GET[self::FILE_URL_PARAMETER])) {
            try {
                self::display($_GET[self::FILE_URL_PARAMETER], isset($_GET[self::MIN_MODE_PARAMETER]), $this->root_path);
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
        else
            die("api not found");
    }

}

?>