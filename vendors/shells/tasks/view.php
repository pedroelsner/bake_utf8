<?php
/**
 * Arquivo que executa o comando 'bake_utf8 view'
 *
 * Compatível com PHP 4 e 5
 *
 * @filesource
 * @author      Pedro Elsner <pedro.elsner@gmail.com>
 * @since       v 1.0
 */

App::import('Controller', 'Controller', false);

include_once dirname(__FILE__) . DS . 'bake_utf8.php';


/**
 * View Task
 *
 * @use         BakeUtf8Task
 * @package     bake_utf8
 * @subpackage  bake_utf8.view_task
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */
class ViewTask extends BakeUtf8Task
{
    
/**
 * Carrega todas as taks
 *
 * @var array
 * @access public
 */
    var $tasks = array(
        'Project',
        'Controller',
        'DbConfig',
        'Template'
    );
    
/**
 * Diret�rio
 *
 * @var string
 * @access public
 */
    var $path = VIEWS;
    
/**
 * @var string
 * @access public
 */
    var $controllerName = null;
    
/**
 * @var string
 * @access public
 */
    var $controllerPath = null;
    
/**
 * @var string
 * @access public
 */
    var $template = null;
    
/**
 * @var array
 * @access public
 */
    var $scaffoldActions = array('index', 'view', 'add', 'edit');
    
/**
 * @var array
 * @access public
 */
    var $noTemplateActions = array('delete', 'excluir');
    
    
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
            $this->__interactive();
        }
        if (empty($this->args[0]))
        {
            return;
        }
        if (!isset($this->connection))
        {
            $this->connection = 'default';
        }
        $controller = $action = $alias = null;
        $this->ControllerName = $this->_controllerName($this->args[0]);
        $this->ControllerPath = $this->_controllerPath($this->ControllerName);

        $this->Project->interactive = false;
        if (strtolower($this->args[0]) == 'all')
        {
            return $this->all();
        }

        if (isset($this->args[1]))
        {
            $this->template = $this->args[1];
        }
        if (isset($this->args[2]))
        {
            $action = $this->args[2];
        }
        if (!$action)
        {
            $action = $this->template;
        }
        if ($action)
        {
            return $this->bake($action, true);
        }

        $vars = $this->__loadController();
        $methods = $this->_methodsToBake();

        foreach ($methods as $method)
        {
            $content = $this->getContent($method, $vars);
            if ($content)
            {
                $this->bake($method, $content);
            }
        }
    }
    
    
/**
 * Methods To Bake
 *
 * @return array
 * @access protected
 */
    function _methodsToBake()
    {
        $methods =  array_diff(
            array_map('strtolower', get_class_methods($this->ControllerName . 'Controller')),
            array_map('strtolower', get_class_methods('appcontroller'))
        );
        $scaffoldActions = false;
        if (empty($methods))
        {
            $scaffoldActions = true;
            $methods = $this->scaffoldActions;
        }
        $adminRoute = $this->Project->getPrefix();
        foreach ($methods as $i => $method)
        {
            if ($adminRoute && isset($this->params['admin']))
            {
                if ($scaffoldActions)
                {
                    $methods[$i] = $adminRoute . $method;
                    continue;
                }
                elseif (strpos($method, $adminRoute) === false)
                {
                    unset($methods[$i]);
                }
            }
            if ($method[0] === '_' || $method == strtolower($this->ControllerName . 'Controller'))
            {
                unset($methods[$i]);
            }
        }
        return $methods;
    }
    
    
/**
 * All
 *
 * Respons�vel pelo comando 'bake_utf8 view all'
 *
 * @access public
 */
    function all()
    {
        $this->Controller->interactive = false;
        $tables = $this->Controller->listAll($this->connection, false);

        $actions = null;
        if (isset($this->args[1]))
        {
            $actions = array($this->args[1]);
        }
        $this->interactive = false;
        foreach ($tables as $table)
        {
            $model = $this->_modelName($table);
            $this->ControllerName = $this->_controllerName($model);
            $this->ControllerPath = Inflector::underscore($this->ControllerName);
            if (App::import('Model', $model))
            {
                $vars = $this->__loadController();
                if (!$actions)
                {
                    $actions = $this->_methodsToBake();
                }
                $this->bakeActions($actions, $vars);
                $actions = null;
            }
        }
    }
    
    
/**
 * Interactive
 *
 * @access private
 */
    function __interactive()
    {
        $this->hr();
        $this->out(sprintf("Bake View\nPath: %s", $this->path));
        $this->hr();

        $this->DbConfig->interactive = $this->Controller->interactive = $this->interactive = true;

        if (empty($this->connection))
        {
            $this->connection = $this->DbConfig->getConfig();
        }

        $this->Controller->connection = $this->connection;
        $this->ControllerName = $this->Controller->getName();

        $this->ControllerPath = strtolower(Inflector::underscore($this->ControllerName));

        $prompt = sprintf(__("Would you like bake to build your views interactively?\nWarning: Choosing no will overwrite %s views if it exist.", true),  $this->ControllerName);
        $interactive = $this->in($prompt, array('y', 'n'), 'n');

        if (strtolower($interactive) == 'n')
        {
            $this->interactive = false;
        }

        $prompt = __("Would you like to create some CRUD views\n(index, add, view, edit) for this controller?\nNOTE: Before doing so, you'll need to create your controller\nand model classes (including associated models).", true);
        $wannaDoScaffold = $this->in($prompt, array('y','n'), 'y');

        $wannaDoAdmin = $this->in(__("Would you like to create the views for admin routing?", true), array('y','n'), 'n');

        if (strtolower($wannaDoScaffold) == 'y' || strtolower($wannaDoAdmin) == 'y')
        {
            $vars = $this->__loadController();
            if (strtolower($wannaDoScaffold) == 'y')
            {
                $actions = $this->scaffoldActions;
                $this->bakeActions($actions, $vars);
            }
            if (strtolower($wannaDoAdmin) == 'y')
            {
                $admin = $this->Project->getPrefix();
                $regularActions = $this->scaffoldActions;
                $adminActions = array();
                foreach ($regularActions as $action) {
                    $adminActions[] = $admin . $action;
                }
                $this->bakeActions($adminActions, $vars);
            }
            $this->hr();
            $this->out();
            $this->out(__("View Scaffolding Complete.\n", true));
        }
        else
        {
            $this->customAction();
        }
    }
    
    
/**
 * Load Controller
 *
 * @access private
 */
    function __loadController()
    {
        if (!$this->ControllerName)
        {
            $this->err(__('Controller not found', true));
        }

        $import = $this->ControllerName;
        if ($this->plugin)
        {
            $import = $this->plugin . '.' . $this->ControllerName;
        }

        if (!App::import('Controller', $import))
        {
            $file = $this->ControllerPath . '_controller.php';
            $this->err(sprintf(__("The file '%s' could not be found.\nIn order to bake a view, you'll need to first create the controller.", true), $file));
            $this->_stop();
        }
        $controllerClassName = $this->ControllerName . 'Controller';
        $controllerObj =& new $controllerClassName();
        $controllerObj->plugin = $this->plugin;
        $controllerObj->constructClasses();
        $modelClass = $controllerObj->modelClass;
        $modelObj =& $controllerObj->{$controllerObj->modelClass};

        if ($modelObj)
        {
            $primaryKey = $modelObj->primaryKey;
            $displayField = $modelObj->displayField;
            $singularVar = Inflector::variable($modelClass);
            $singularHumanName = $this->_singularHumanName($this->ControllerName);
            $schema = $modelObj->schema(true);
            $fields = array_keys($schema);
            $associations = $this->__associations($modelObj);
        }
        else
        {
            $primaryKey = $displayField = null;
            $singularVar = Inflector::variable(Inflector::singularize($this->ControllerName));
            $singularHumanName = $this->_singularHumanName($this->ControllerName);
            $fields = $schema = $associations = array();
        }
        $pluralVar = Inflector::variable($this->ControllerName);
        $pluralHumanName = $this->_pluralHumanName($this->ControllerName);

        return compact('modelClass', 'schema', 'primaryKey', 'displayField', 'singularVar', 'pluralVar',
                'singularHumanName', 'pluralHumanName', 'fields','associations');
    }
    
    
/**
 * Bake Actions
 *
 * @param array $actions
 * @param array $vars
 * @access public
 */
    function bakeActions($actions, $vars)
    {
        foreach ($actions as $action)
        {
            $content = $this->getContent($action, $vars);
            $this->bake($action, $content);
        }
    }
    
    
/**
 * Custom Action
 *
 * @access public
 */
    function customAction()
    {
        $action = '';
        while ($action == '')
        {
            $action = $this->in(__('Action Name? (use lowercase_underscored function name)', true));
            if ($action == '')
            {
                $this->out(__('The action name you supplied was empty. Please try again.', true));
            }
        }
        $this->out();
        $this->hr();
        $this->out(__('The following view will be created:', true));
        $this->hr();
        $this->out(sprintf(__('Controller Name: %s', true), $this->ControllerName));
        $this->out(sprintf(__('Action Name:     %s', true), $action));
        $this->out(sprintf(__('Path:            %s', true), $this->params['app'] . DS . $this->ControllerPath . DS . Inflector::underscore($action) . ".ctp"));
        $this->hr();
        $looksGood = $this->in(__('Look okay?', true), array('y','n'), 'y');
        if (strtolower($looksGood) == 'y')
        {
            $this->bake($action);
            $this->_stop();
        }
        else
        {
            $this->out(__('Bake Aborted.', true));
        }
    }
    
    
/**
 * Bake
 *
 * @param string $action
 * @param string $content
 * @return boolean
 * @access public
 */
    function bake($action, $content = '')
    {
        if ($content === true)
        {
            $content = $this->getContent($action);
        }
        if (empty($content))
        {
            return false;
        }
        $path = $this->getPath();
        $filename = $path . $this->ControllerPath . DS . Inflector::underscore($action) . '.ctp';
        
        if( $this->createFile($filename, $content) )
        {
                
            /**
            * Convert para UTF8
            */
            $this->out(sprintf(__('Converting file to UTF-8...', true)));
            $this->convertToUTF8($filename);
            $this->removeBOM($filename);
            
            return true;
        }
        
        return false;
    }
    
    
/**
 * Get Content
 *
 * @param string $action
 * @param array $vars
 * #return boolean
 * @access public
 */
    function getContent($action, $vars = null)
    {
        if (!$vars)
        {
            $vars = $this->__loadController();
        }

        $this->Template->set('action', $action);
        $this->Template->set('plugin', $this->plugin);
        $this->Template->set($vars);
        $template = $this->getTemplate($action);
        if ($template)
        {
            return $this->Template->generate('views', $template);
        }
        return false;
    }
    
    
/**
 * Get Template
 *
 * @param string $action
 * #return boolean
 * @access public
 */
    function getTemplate($action)
    {
        if ($action != $this->template && in_array($action, $this->noTemplateActions))
        {
            return false;
        }
        if (!empty($this->template) && $action != $this->template)
        {
            return $this->template;
        } 
        $template = $action;
        $prefixes = Configure::read('Routing.prefixes');
        foreach ((array)$prefixes as $prefix)
        {
            if (strpos($template, $prefix) !== false)
            {
                $template = str_replace($prefix . '_', '', $template);
            }
        }
        if (in_array($template, array('add', 'edit', 'adicionar', 'editar')))
        {
            $template = 'form';
        }
        elseif (preg_match('@(_add|_edit|_adicionar|_editar)$@', $template))
        {
            $template = str_replace(array('_add', '_edit', '_adicionar', '_editar'), '_form', $template);
        }
        return $template;
    }
    
    
/**
 * Help
 *
 * Responde o comando 'bake_utf8 view help'
 *
 * @access public
 */
    function help() {
        $this->hr();
        $this->out("Usage: cake bake_utf8 view <arg1> <arg2>...");
        $this->hr();
        $this->out('Arguments:');
        $this->out();
        $this->out("<controller>");
        $this->out("\tName of the controller views to bake. Can use Plugin.name");
        $this->out("\tas a shortcut for plugin baking.");
        $this->out();
        $this->out("<action>");
        $this->out("\tName of the action view to bake");
        $this->out();
        $this->out('Commands:');
        $this->out();
        $this->out("view <controller>");
        $this->out("\tWill read the given controller for methods");
        $this->out("\tand bake corresponding views.");
        $this->out("\tUsing the -admin flag will only bake views for actions");
        $this->out("\tthat begin with Routing.admin.");
        $this->out("\tIf var scaffold is found it will bake the CRUD actions");
        $this->out("\t(index,view,add,edit)");
        $this->out();
        $this->out("view <controller> <action>");
        $this->out("\tWill bake a template. core templates: (index, add, edit, view)");
        $this->out();
        $this->out("view <controller> <template> <alias>");
        $this->out("\tWill use the template specified");
        $this->out("\tbut name the file based on the alias");
        $this->out();
        $this->out("view all");
        $this->out("\tBake all CRUD action views for all controllers.");
        $this->out("\tRequires that models and controllers exist.");
        $this->_stop();
    }

    
/**
 * Associations
 *
 * @param object $model
 * #return array
 * @access private
 */
    function __associations(&$model)
    {
        $keys = array('belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany');
        $associations = array();

        foreach ($keys as $key => $type)
        {
            foreach ($model->{$type} as $assocKey => $assocData)
            {
                $associations[$type][$assocKey]['primaryKey'] = $model->{$assocKey}->primaryKey;
                $associations[$type][$assocKey]['displayField'] = $model->{$assocKey}->displayField;
                $associations[$type][$assocKey]['foreignKey'] = $assocData['foreignKey'];
                $associations[$type][$assocKey]['controller'] = Inflector::pluralize(Inflector::underscore($assocData['className']));
                $associations[$type][$assocKey]['fields'] =  array_keys($model->{$assocKey}->schema(true));
            }
        }
        return $associations;
    }
    
    
}
