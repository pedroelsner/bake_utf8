<?php
/**
 * Arquivo que executa o comando 'bake_utf8 test_utf8'
 *
 * Compatível com PHP 4 e 5
 *
 * @filesource
 * @author           Pedro Elsner <pedro.elsner@gmail.com>
 * @since       v 1.0
 */

include_once dirname(__FILE__) . DS . 'bake_utf8.php';


/**
 * Test Utf8 Task
 *
 * @use         BakeUtf8Task
 * @package        bake_utf8
 * @subpackage     bake_utf8.test_utf8_task
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */
class TestUtf8Task extends BakeUtf8Task
{

/**
 * Diret�rio
 *
 * @var string
 * @access public
 */
    var $path = TESTS;
    
/**
 * Carrega todas as taks
 *
 * @var array
 * @access public
 */
    var $tasks = array('Template');
    
/**
 * @var array
 * @access public
 */
    var $classTypes =  array(
        'Model',
        'Controller',
        'Component',
        'Behavior',
        'Helper'
    );
    
/**
 * @var array
 * @access public
 */
    var $_fixtures = array();
    
    
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

        if (count($this->args) == 1)
        {
            $this->__interactive($this->args[0]);
        }

        if (count($this->args) > 1)
        {
            $type = Inflector::underscore($this->args[0]);
            if ($this->bake($type, $this->args[1]))
            {
                $this->out('done');
            }
        }
    }
    
    
/** 
 * Interactive
 *
 * @param string $type
 * @return
 * @access private
 */
    function __interactive($type = null)
    {
        $this->interactive = true;
        $this->hr();
        $this->out(__('Bake Tests', true));
        $this->out(sprintf(__("Path: %s", true), $this->path));
        $this->hr();
        
        if ($type)
        {
            $type = Inflector::camelize($type);
            if (!in_array($type, $this->classTypes))
            {
                $this->error(sprintf('Incorrect type provided.  Please choose one of %s', implode(', ', $this->classTypes)));
            }
        }
        else
        {
            $type = $this->getObjectType();
        }
        $className = $this->getClassName($type);
        return $this->bake($type, $className);
    }
    
    
/** 
 * Bake
 *
 * @param string $type
 * @param string $className
 * @return boolean
 * @access private
 */
    function bake($type, $className) {
        if ($this->typeCanDetectFixtures($type) && $this->isLoadableClass($type, $className)) {
            $this->out(__('Bake is detecting possible fixtures..', true));
            $testSubject =& $this->buildTestSubject($type, $className);
            $this->generateFixtureList($testSubject);
        } elseif ($this->interactive) {
            $this->getUserFixtures();
        }
        $fullClassName = $this->getRealClassName($type, $className);

        $methods = array();
        if (class_exists($fullClassName)) {
            $methods = $this->getTestableMethods($fullClassName);
        }
        $mock = $this->hasMockClass($type, $fullClassName);
        $construction = $this->generateConstructor($type, $fullClassName);

        $plugin = null;
        if ($this->plugin) {
            $plugin = $this->plugin . '.';
        }

        $this->Template->set('fixtures', $this->_fixtures);
        $this->Template->set('plugin', $plugin);
        $this->Template->set(compact('className', 'methods', 'type', 'fullClassName', 'mock', 'construction'));
        $out = $this->Template->generate('classes', 'test');

        $filename = $this->testCaseFileName($type, $className);
        $made = $this->createFile($filename, $out);
        if ($made) {
            return $out;
        }
        return false;
    }
    
    
/** 
 * Get Objetct Type
 *
 * @return string
 * @access public
 */
    function getObjectType()
    {
        $this->hr();
        $this->out(__("Select an object type:", true));
        $this->hr();

        $keys = array();
        foreach ($this->classTypes as $key => $option)
        {
            $this->out(++$key . '. ' . $option);
            $keys[] = $key;
        }
        $keys[] = 'q';
        $selection = $this->in(__("Enter the type of object to bake a test for or (q)uit", true), $keys, 'q');
        if ($selection == 'q')
        {
            return $this->_stop();
        }
        return $this->classTypes[$selection - 1];
    }
    
    
/** 
 * Get Class Name
 *
 * @param string $objectType
 * @return string
 * @access public
 */
    function getClassName($objectType)
    {
        $type = strtolower($objectType);
        if ($this->plugin)
        {
            $path = Inflector::pluralize($type);
            if ($type === 'helper')
            {
                $path = 'views' . DS . $path;
            }
            elseif ($type === 'component')
            {
                $path = 'controllers' . DS . $path;
            }
            elseif ($type === 'behavior')
            {
                $path = 'models' . DS . $path;
            }
            $options = App::objects($type, App::pluginPath($this->plugin) . $path, false);
        }
        else
        {
            $options = App::objects($type);
        }
        $this->out(sprintf(__('Choose a %s class', true), $objectType));
        $keys = array();
        foreach ($options as $key => $option)
        {
            $this->out(++$key . '. ' . $option);
            $keys[] = $key;
        }
        $selection = $this->in(__('Choose an existing class, or enter the name of a class that does not exist', true));
        if (isset($options[$selection - 1]))
        {
            return $options[$selection - 1];
        }
        return $selection;
    }
    
    
/** 
 * Type Can Detect Fixtures
 *
 * @param string $type
 * @return boolean
 * @access public
 */
    function typeCanDetectFixtures($type) {
        $type = strtolower($type);
        return ($type == 'controller' || $type == 'model');
    }
    
    
/** 
 * Is Loadable Class
 *
 * @param string $type
 * @param string $class
 * @return object
 * @access public
 */
    function isLoadableClass($type, $class)
    {
        return App::import($type, $class);
    }
    
    
/**
 * Build Test Subject
 *
 * @param string $type
 * @param string $class
 * @return object
 * @access public
 */
    function &buildTestSubject($type, $class)
    {
        ClassRegistry::flush();
        App::import($type, $class);
        $class = $this->getRealClassName($type, $class);
        if (strtolower($type) == 'model')
        {
            $instance =& ClassRegistry::init($class);
        }
        else
        {
            $instance =& new $class();
        }
        return $instance;
    }
    
    
/**
 * Get Real Class Name
 *
 * @param string $type
 * @param string $class
 * @return string
 * @access public
 */
    function getRealClassName($type, $class) {
        if (strtolower($type) == 'model') {
            return $class;
        }
        return $class . $type;
    }
    
    
/**
 * Get Testable Methods
 *
 * @param string $className
 * @return string
 * @access public
 */
    function getTestableMethods($className)
    {
        $classMethods = get_class_methods($className);
        $parentMethods = get_class_methods(get_parent_class($className));
        $thisMethods = array_diff($classMethods, $parentMethods);
        $out = array();
        foreach ($thisMethods as $method)
        {
            if (substr($method, 0, 1) != '_' && $method != strtolower($className))
            {
                $out[] = $method;
            }
        }
        return $out;
    }
    
    
/**
 * Generate Fixture List
 *
 * @param object $subject
 * @return array
 * @access public
 */
    function generateFixtureList(&$subject)
    {
        $this->_fixtures = array();
        if (is_a($subject, 'Model'))
        {
            $this->_processModel($subject);
        }
        elseif (is_a($subject, 'Controller'))
        {
            $this->_processController($subject);
        }
        return array_values($this->_fixtures);
    }
    
    
/**
 * Process Model
 *
 * @param object $subject
 * @access protected
 */
    function _processModel(&$subject)
    {
        $this->_addFixture($subject->name);
        $associated = $subject->getAssociated();
        foreach ($associated as $alias => $type)
        {
            $className = $subject->{$alias}->name;
            if (!isset($this->_fixtures[$className]))
            {
                $this->_processModel($subject->{$alias});
            }
            if ($type == 'hasAndBelongsToMany')
            {
                $joinModel = Inflector::classify($subject->hasAndBelongsToMany[$alias]['joinTable']);
                if (!isset($this->_fixtures[$joinModel]))
                {
                    $this->_processModel($subject->{$joinModel});
                }
            }
        }
    }
    
    
/**
 * Process Controller
 *
 * @param object $subject
 * @access protected
 */
    function _processController(&$subject)
    {
        $subject->constructClasses();
        $models = array(Inflector::classify($subject->name));
        if (!empty($subject->uses))
        {
            $models = $subject->uses;
        }
        foreach ($models as $model)
        {
            $this->_processModel($subject->{$model});
        }
    }
    
    
/**
 * Add Fixture
 *
 * @param string $name
 * @access protected
 */
    function _addFixture($name)
    {
        $parent = get_parent_class($name);
        $prefix = 'app.';
        if (strtolower($parent) != 'appmodel' && strtolower(substr($parent, -8)) == 'appmodel')
        {
            $pluginName = substr($parent, 0, strlen($parent) -8);
            $prefix = 'plugin.' . Inflector::underscore($pluginName) . '.';
        }
        $fixture = $prefix . Inflector::underscore($name);
        $this->_fixtures[$name] = $fixture;
    }
    
    
/**
 * Get User Fixture
 *
 * @return array
 * @access public
 */
    function getUserFixtures()
    {
        $proceed = $this->in(__('Bake could not detect fixtures, would you like to add some?', true), array('y','n'), 'n');
        $fixtures = array();
        if (strtolower($proceed) == 'y')
        {
            $fixtureList = $this->in(__("Please provide a comma separated list of the fixtures names you'd like to use.\nExample: 'app.comment, app.post, plugin.forums.post'", true));
            $fixtureListTrimmed = str_replace(' ', '', $fixtureList);
            $fixtures = explode(',', $fixtureListTrimmed);
        }
        $this->_fixtures = array_merge($this->_fixtures, $fixtures);
        return $fixtures;
    }
    
    
/**
 * Has Mock Class
 *
 * @param string $type
 * @return string
 * @access public
 */
    function hasMockClass($type) {
        $type = strtolower($type);
        return $type == 'controller';
    }
    
    
/**
 * Generate Constructor
 *
 * @param string $type
 * @param string $fullClassName
 * @return string
 * @access public
 */
    function generateConstructor($type, $fullClassName)
    {
        $type = strtolower($type);
        if ($type == 'model')
        {
            return "ClassRegistry::init('$fullClassName');\n";
        }
        if ($type == 'controller')
        {
            $className = substr($fullClassName, 0, strlen($fullClassName) - 10);
            return "new Test$fullClassName();\n\t\t\$this->{$className}->constructClasses();\n";
        }
        return "new $fullClassName();\n";
    }
    
    
/**
 * Test Case File Name
 *
 * @param string $type
 * @param string $className
 * @return string
 * @access public
 */
    function testCaseFileName($type, $className)
    {
        $path = $this->getPath();;
        $path .= 'cases' . DS . strtolower($type) . 's' . DS;
        if (strtolower($type) == 'controller')
        {
            $className = $this->getRealClassName($type, $className);
        }
        return $path . Inflector::underscore($className) . '.test.php';
    }

    
/**
 * Help
 *
 * Responde o comando 'bake_utf8 test_utf8 help'
 *
 * @access public
 */
    function help() {
        $this->hr();
        $this->out("Usage: cake bake test <type> <class>");
        $this->hr();
        $this->out('Commands:');
        $this->out("");
        $this->out("test model post\n\tbakes a test case for the post model.");
        $this->out("");
        $this->out("test controller comments\n\tbakes a test case for the comments controller.");
        $this->out("");
        $this->out('Arguments:');
        $this->out("\t<type>   Can be any of the following 'controller', 'model', 'helper',\n\t'component', 'behavior'.");
        $this->out("\t<class>  Any existing class for the chosen type.");
        $this->out("");
        $this->out("Parameters:");
        $this->out("\t-plugin  CamelCased name of plugin to bake tests for.");
        $this->out("");
        $this->_stop();
    }
}
