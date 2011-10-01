<?php
/**
 * Arquivo que executa o comando 'bake_utf8 controller_utf8'
 *
 * Compatível com PHP 4 e 5
 *
 * @filesource
 * @author        Pedro Elsner <pedro.elsner@gmail.com>
 * @since       v 1.0
 */

include_once dirname(__FILE__) . DS . 'bake_utf8.php';


/**
 * Controller Utf8 Task
 *
 * @use         BakeUtf8Task
 * @package     bake_utf8
 * @subpackage  bake_utf8.controller_utf8_task
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */
class ControllerUtf8Task extends BakeUtf8Task
{

/**
 * Carrega todas as taks
 *
 * @var array
 * @access public
 */
    var $tasks = array(
        'ModelUtf8',
        'TestUtf8',
        'Template',
        'DbConfig',
        'Project'
    );
    
/**
 * Diretório
 *
 * @var string
 * @access public
 */
    var $path = CONTROLLERS;
    
    
/**
 * Initialize
 *
 * @access public
 */
    function initialize()
    {
    
    }
    
    
/**
 * Execute
 *
 * @access public
 */
    function execute()
    {
        if (empty($this->args))
        {
            return $this->__interactive();
        }

        if (isset($this->args[0]))
        {
            if (!isset($this->connection))
            {
                $this->connection = 'default';
            }
            if (strtolower($this->args[0]) == 'all')
            {
                return $this->all();
            }

            $controller = $this->_controllerName($this->args[0]);
            $actions = 'scaffold';

            if (!empty($this->args[1]) && ($this->args[1] == 'public' || $this->args[1] == 'scaffold'))
            {
                $this->out(__('Baking basic crud methods for ', true) . $controller);
                $actions = $this->bakeActions($controller);
            }
            elseif (!empty($this->args[1]) && $this->args[1] == 'admin')
            {
                $admin = $this->Project->getPrefix();
                if ($admin)
                {
                    $this->out(sprintf(__('Adding %s methods', true), $admin));
                    $actions = $this->bakeActions($controller, $admin);
                }
            }

            if (!empty($this->args[2]) && $this->args[2] == 'admin')
            {
                $admin = $this->Project->getPrefix();
                if ($admin)
                {
                    $this->out(sprintf(__('Adding %s methods', true), $admin));
                    $actions .= "\n" . $this->bakeActions($controller, $admin);
                }
            }

            if ($this->bake($controller, $actions))
            {
                if ($this->_checkUnitTest())
                {
                    $this->bakeTest($controller);
                }
            }
        }
    }

    
/**
 * All
 *
 * Responde ao comando 'bake_utf8 controller_utf8 all'
 *
 * @access public
 */
    function all()
    {
        $this->interactive = false;
        $this->listAll($this->connection, false);
        ClassRegistry::config('Model', array('ds' => $this->connection));
        $unitTestExists = $this->_checkUnitTest();
        foreach ($this->__tables as $table)
        {
            $model = $this->_modelName($table);
            $controller = $this->_controllerName($model);
            if (App::import('Model', $model))
            {
                $actions = $this->bakeActions($controller);
                if ($this->bake($controller, $actions) && $unitTestExists)
                {
                    $this->bakeTest($controller);
                }
            }
        }
    }

    
/**
 * Interactive
 *
 * @return 
 * @access private
 */
    function __interactive() {
        $this->interactive = true;
        $this->hr();
        $this->out(sprintf(__("Bake Controller\nPath: %s", true), $this->path));
        $this->hr();

        if (empty($this->connection))
        {
            $this->connection = $this->DbConfig->getConfig();
        }

        $controllerName = $this->getName();
        $this->hr();
        $this->out(sprintf(__('Baking %sController', true), $controllerName));
        $this->hr();

        $helpers = $components = array();
        $actions = '';
        $wannaUseSession = 'y';
        $wannaBakeAdminCrud = 'n';
        $useDynamicScaffold = 'n';
        $wannaBakeCrud = 'y';

        $controllerFile = strtolower(Inflector::underscore($controllerName));

        $question[] = __("Would you like to build your controller interactively?", true);
        if (file_exists($this->path . $controllerFile .'_controller.php'))
        {
            $question[] = sprintf(__("Warning: Choosing no will overwrite the %sController.", true), $controllerName);
        }
        $doItInteractive = $this->in(implode("\n", $question), array('y','n'), 'y');

        if (strtolower($doItInteractive) == 'y')
        {
            $this->interactive = true;
            $useDynamicScaffold = $this->in(
                __("Would you like to use dynamic scaffolding?", true), array('y','n'), 'n'
            );

            if (strtolower($useDynamicScaffold) == 'y')
            {
                $wannaBakeCrud = 'n';
                $actions = 'scaffold';
            }
            else
            {
                list($wannaBakeCrud, $wannaBakeAdminCrud) = $this->_askAboutMethods();

                $helpers = $this->doHelpers();
                $components = $this->doComponents();

                $wannaUseSession = $this->in(
                    __("Would you like to use Session flash messages?", true), array('y','n'), 'y'
                );
            }
        }
        else
        {
            list($wannaBakeCrud, $wannaBakeAdminCrud) = $this->_askAboutMethods();
        }

        if (strtolower($wannaBakeCrud) == 'y')
        {
            $actions = $this->bakeActions($controllerName, null, strtolower($wannaUseSession) == 'y');
        }
        if (strtolower($wannaBakeAdminCrud) == 'y')
        {
            $admin = $this->Project->getPrefix();
            $actions .= $this->bakeActions($controllerName, $admin, strtolower($wannaUseSession) == 'y');
        }

        $baked = false;
        if ($this->interactive === true)
        {
            $this->confirmController($controllerName, $useDynamicScaffold, $helpers, $components);
            $looksGood = $this->in(__('Look okay?', true), array('y','n'), 'y');

            if (strtolower($looksGood) == 'y')
            {
                $baked = $this->bake($controllerName, $actions, $helpers, $components);
                if ($baked && $this->_checkUnitTest())
                {
                    $this->bakeTest($controllerName);
                }
            }
        }
        else
        {
            $baked = $this->bake($controllerName, $actions, $helpers, $components);
            if ($baked && $this->_checkUnitTest())
            {
                $this->bakeTest($controllerName);
            }
        }
        return $baked;
    }

    
/**
 * Confirm Controller
 *    
 * @param string $controllerName
 * @param boolean $useDynamicScaffold
 * @param array $helpers
 * @param array $components
 * @access public
 */
    function confirmController($controllerName, $useDynamicScaffold, $helpers, $components) {
        $this->out();
        $this->hr();
        $this->out(__('The following controller will be created:', true));
        $this->hr();
        $this->out(sprintf(__("Controller Name:\n\t%s", true), $controllerName));

        if (strtolower($useDynamicScaffold) == 'y')
        {
            $this->out("var \$scaffold;");
        }

        $properties = array(
            'helpers' => __("Helpers:", true),
            'components' => __('Components:', true),
        );

        foreach ($properties as $var => $title)
        {
            if (count($$var))
            {
                $output = '';
                $length = count($$var);
                foreach ($$var as $i => $propElement)
                {
                    if ($i != $length -1)
                    {
                        $output .= ucfirst($propElement) . ', ';
                    }
                    else
                    {
                        $output .= ucfirst($propElement);
                    }
                }
                $this->out($title . "\n\t" . $output);
            }
        }
        $this->hr();
    }

    
/**
 * Ask About Methods
 *
 * @return array
 * @access protected
 */
    function _askAboutMethods()
    {
        $wannaBakeCrud = $this->in(
            __("Would you like to create some basic class methods \n(index(), add(), view(), edit())?", true),
            array('y','n'), 'n'
        );
        $wannaBakeAdminCrud = $this->in(
            __("Would you like to create the basic class methods for admin routing?", true),
            array('y','n'), 'n'
        );
        return array($wannaBakeCrud, $wannaBakeAdminCrud);
    }

    
/**
 * Bake Actions
 *
 * @param string $controllerName
 * @param string $admin
 * @param boolean $wannaUseSession
 * @return string
 * @access public
 */
    function bakeActions($controllerName, $admin = null, $wannaUseSession = true)
    {
        $currentModelName = $modelImport = $this->_modelName($controllerName);
        $plugin = $this->plugin;
        if ($plugin)
        {
            $modelImport = $plugin . '.' . $modelImport;
        }
        if (!App::import('Model', $modelImport))
        {
            $this->err(__('You must have a model for this class to build basic methods. Please try again.', true));
            $this->_stop();
        }

        $modelObj =& ClassRegistry::init($currentModelName);
        $controllerPath = $this->_controllerPath($controllerName);
        $pluralName = $this->_pluralName($currentModelName);
        $singularName = Inflector::variable($currentModelName);
        $singularHumanName = $this->_singularHumanName($controllerName);
        $pluralHumanName = $this->_pluralName($controllerName);

        $this->Template->set(compact('plugin', 'admin', 'controllerPath', 'pluralName', 'singularName', 'singularHumanName',
            'pluralHumanName', 'modelObj', 'wannaUseSession', 'currentModelName'));
        $actions = $this->Template->generate('actions', 'controller_actions');
        return $actions;
    }

    
/**
 * Bake
 *
 * @param string $controllerName
 * @param string $actions
 * @param array $helpers
 * @param array $components
 * @return boolean
 * @access public
 */
    function bake($controllerName, $actions = '', $helpers = null, $components = null)
    {
        $isScaffold = ($actions === 'scaffold') ? true : false;

        $this->Template->set('plugin', Inflector::camelize($this->plugin));
        $this->Template->set(compact('controllerName', 'actions', 'helpers', 'components', 'isScaffold'));
        $contents = $this->Template->generate('classes', 'controller');

        $path = $this->getPath();
        $filename = $path . $this->_controllerPath($controllerName) . '_controller.php';
        if ($this->createFile($filename, $contents))
        {
            
            /**
             * Convert para UTF8
             */
            $this->out(sprintf(__('Converting file to UTF-8...', true)));
            $this->convertToUTF8($filename);
            $this->removeBOM($filename);
            
            return $contents;
        }
        return false;
    }

    
/**
 * Bake Test
 *
 * @param string $className
 * @return boolean
 * @access public
 */
    function bakeTest($className) {
        $this->TestUtf8->plugin = $this->plugin;
        $this->TestUtf8->connection = $this->connection;
        $this->TestUtf8->interactive = $this->interactive;
        return $this->TestUtf8->bake('Controller', $className);
    }
    
    
/**
 * Do Helpers
 *
 * @access public
 */
    function doHelpers()
    {
        return $this->_doPropertyChoices(
            __("Would you like this controller to use other helpers\nbesides HtmlHelper and FormHelper?", true),
            __("Please provide a comma separated list of the other\nhelper names you'd like to use.\nExample: 'Ajax, Javascript, Time'", true)
        );
    }
    
    
/**
 * Do Components
 *
 * @access public
 */
    function doComponents()
    {
        return $this->_doPropertyChoices(
            __("Would you like this controller to use any components?", true),
            __("Please provide a comma separated list of the component names you'd like to use.\nExample: 'Acl, Security, RequestHandler'", true)
        );
    }

    
/**
 * Do Property Choices
 *
 * @param array $prompt
 * @param array $example
 * @return array
 * @access protected
 */
    function _doPropertyChoices($prompt, $example)
    {
        $proceed = $this->in($prompt, array('y','n'), 'n');
        $property = array();
        if (strtolower($proceed) == 'y')
        {
            $propertyList = $this->in($example);
            $propertyListTrimmed = str_replace(' ', '', $propertyList);
            $property = explode(',', $propertyListTrimmed);
        }
        return array_filter($property);
    }

    
/**
 * List All
 *
 * @param string $useDbConfig
 * @access public
 */
    function listAll($useDbConfig = null)
    {
        if (is_null($useDbConfig))
        {
            $useDbConfig = $this->connection;
        }
        $this->__tables = $this->ModelUtf8->getAllTables($useDbConfig);

        if ($this->interactive == true)
        {
            $this->out(__('Possible Controllers based on your current database:', true));
            $this->_controllerNames = array();
            $count = count($this->__tables);
            for ($i = 0; $i < $count; $i++)
            {
                $this->_controllerNames[] = $this->_controllerName($this->_modelName($this->__tables[$i]));
                $this->out($i + 1 . ". " . $this->_controllerNames[$i]);
            }
            return $this->_controllerNames;
        }
        return $this->__tables;
    }

    
/**
 * Get Name
 *
 * @param string $useDbConfig
 * #return string
 * @access public
 */
    function getName($useDbConfig = null) {
        $controllers = $this->listAll($useDbConfig);
        $enteredController = '';

        while ($enteredController == '') {
            $enteredController = $this->in(__("Enter a number from the list above,\ntype in the name of another controller, or 'q' to exit", true), null, 'q');

            if ($enteredController === 'q') {
                $this->out(__("Exit", true));
                $this->_stop();
            }

            if ($enteredController == '' || intval($enteredController) > count($controllers)) {
                $this->err(__("The Controller name you supplied was empty,\nor the number you selected was not an option. Please try again.", true));
                $enteredController = '';
            }
        }

        if (intval($enteredController) > 0 && intval($enteredController) <= count($controllers) ) {
            $controllerName = $controllers[intval($enteredController) - 1];
        } else {
            $controllerName = Inflector::camelize($enteredController);
        }
        return $controllerName;
    }

    
/**
 * Help
 *
 * Responde o comando 'bake_utf8 controller_utf8 help'
 *
 * @access public
 */
    function help() {
        $this->hr();
        $this->out("Usage: cake bake_utf8 controller_utf8 <arg1> <arg2>...");
        $this->hr();
        $this->out('Arguments:');
        $this->out();
        $this->out("<name>");
        $this->out("\tName of the controller to bake. Can use Plugin.name");
        $this->out("\tas a shortcut for plugin baking.");
        $this->out();
        $this->out('Commands:');
        $this->out();
        $this->out("controller_utf8 <name>");
        $this->out("\tbakes controller with var \$scaffold");
        $this->out();
        $this->out("controller_utf8 <name> public");
        $this->out("\tbakes controller with basic crud actions");
        $this->out("\t(index, view, add, edit, delete)");
        $this->out();
        $this->out("controller_utf8 <name> admin");
        $this->out("\tbakes a controller with basic crud actions for one of the");
        $this->out("\tConfigure::read('Routing.prefixes') methods.");
        $this->out();
        $this->out("controller_utf8 <name> public admin");
        $this->out("\tbakes a controller with basic crud actions for one");
        $this->out("\tConfigure::read('Routing.prefixes') and non admin methods.");
        $this->out("\t(index, view, add, edit, delete,");
        $this->out("\tadmin_index, admin_view, admin_edit, admin_add, admin_delete)");
        $this->out();
        $this->out("controller_utf8 all");
        $this->out("\tbakes all controllers with CRUD methods.");
        $this->out();
        $this->_stop();
    }
    
}
