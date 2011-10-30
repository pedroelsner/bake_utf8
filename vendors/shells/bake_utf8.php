<?php
/**
 * Arquivo que responde ao comando 'bake_utf8'
 *
 * Compatível com PHP 4 e 5
 *
 * @filesource
 * @author      Pedro Elsner <pedro.elsner@gmail.com>
 * @since       v 1.0
 */


/**
 * Bake  Shell
 *
 * @use         Shell
 * @package     bake_utf8
 * @subpackage  bake_utf8.bake_utf8_shell
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */ 
class BakeUtf8Shell extends Shell
{
    
/**
 * Carrega todas as taks
 *
 * @var array
 * @access public
 */
    var $tasks = array(
        'Project',
        'DbConfig',
        'Model',
        'Controller',
        'View',
        'Plugin',
        'Plugin',
        'Fixture',
        'Test'
    );

    
/**
 * Main
 *
 * @return boolean
 * @access public
 */
    function main()
    {
        if (!is_dir($this->DbConfig->path))
        {
            if ($this->Project->execute())
            {
                $this->DbConfig->path = $this->params['working'] . DS . 'config' . DS;
            }
            else
            {
                return false;
            }
        }

        if (!config('database'))
        {
            $this->out(__("Your database configuration was not found. Take a moment to create one.", true));
            $this->args = null;
            return $this->DbConfig->execute();
        }
        $this->out('Interactive Bake Utf8 Shell');
        $this->hr();
        $this->out('[D]atabase Configuration');
        $this->out('[M]odel');
        $this->out('[V]iew');
        $this->out('[C]ontroller');
        $this->out('[P]roject');
        $this->out('[F]ixture');
        $this->out('[T]est case');
        $this->out('[Q]uit');

        $classToBake = strtoupper($this->in(__('What would you like to Bake in Utf8?', true), array('D', 'M', 'V', 'C', 'P', 'F', 'T', 'Q')));
        switch ($classToBake)
        {
            case 'D':
                $this->DbConfig->execute();
                break;
            case 'M':
                $this->Model->execute();
                break;
            case 'V':
                $this->View->execute();
                break;
            case 'C':
                $this->Controller->execute();
                break;
            case 'P':
                $this->Project->execute();
                break;
            case 'F':
                $this->Fixture->execute();
                break;
            case 'T':
                $this->Test->execute();
                break;
            case 'Q':
                exit(0);
                break;
            default:
                $this->out(__('You have made an invalid selection. Please choose a type of class to Bake by entering D, M, V, F, T, or C.', true));
        }
        $this->hr();
        $this->main();
    }

    
/**
 * All
 *
 * Responde ao comando 'bake_utf8 all'
 *
 * @access public
 */
    function all()
    {
        $this->hr();
        $this->out('Bake All');
        $this->hr();

        if (!isset($this->params['connection']) && empty($this->connection))
        {
            $this->connection = $this->DbConfig->getConfig();
        }
        
        
        if (empty($this->args))
        {
            $this->Model->interactive = true;
            $name = $this->Model->getName($this->connection);
        }
        
        
        foreach (array('Model', 'Controller', 'View') as $task)
        {
            $this->{$task}->connection = $this->connection;
            $this->{$task}->interactive = false;
        }

        if (!empty($this->args[0]))
        {
            $name = $this->args[0];
        }

        $modelExists = false;
        $model = $this->_modelName($name);
        if (App::import('Model', $model))
        {
            $object = new $model();
            $modelExists = true;
        }
        else
        {
            App::import('Model', 'Model', false);
            $object = new Model(array('name' => $name, 'ds' => $this->connection));
        }
        
        
        $modelBaked = $this->Model->bake($object, false);
        
        
        if ($modelBaked && $modelExists === false)
        {
            $this->out(sprintf(__('%s Model was baked.', true), $model));
            if ($this->_checkUnitTest())
            {
                $this->Model->bakeFixture($model);
                $this->Model->bakeTest($model);
            }
            
            $modelExists = true;
        }

        if ($modelExists === true)
        {
            $controller = $this->_controllerName($name);
            if ($this->Controller->bake($controller, $this->Controller->bakeActions($controller))) {
                $this->out(sprintf(__('%s Controller was baked.', true), $name));
                if ($this->_checkUnitTest())
                {
                    $this->Controller->bakeTest($controller);
                }
                
            }
            if (App::import('Controller', $controller))
            {
                $this->View->args = array($controller);
                $this->View->execute();
                $this->out(sprintf(__('%s Views were baked.', true), $name));
                
            }
            $this->out(__('Bake All complete', true));
            array_shift($this->args);
        }
        else
        {
            $this->err(__('Bake All could not continue without a valid model', true));
        }
        $this->_stop();
    }

    
/**
 * Help
 *
 * Responde ao comando 'bake_utf8 help'
 *
 * @access public
 */
    function help()
    {
        $this->out('CakePHP Bake UTF8:');
        $this->hr();
        $this->out('The Bake UTF8 plugin script generates controllers, views and models for your application.');
        $this->out('If run with no command line arguments, Bake guides the user through the class');
        $this->out('creation process. You can customize the generation process by telling Bake');
        $this->out('where different parts of your application are using command line arguments.');
        $this->hr();
        $this->out('This generates all files in UTF8 without BOM.');
        $this->hr();
        $this->out("Usage: cake bake_utf8 <command> <arg1> <arg2>...");
        $this->hr();
        $this->out('Params:');
        $this->out("\t-app <path> Absolute/Relative path to your app folder.\n");
        $this->out('Commands:');
        $this->out("\n\tbake_utf8 help\n\t\tshows this help message.");
        $this->out("\n\tbake_utf8 all <name>\n\t\tbakes complete MVC. optional <name> of a Model");
        $this->out("\n\tbake_utf8 project <path>\n\t\tbakes a new app folder in the path supplied\n\t\tor in current directory if no path is specified");
        $this->out("\n\tbake_utf8 plugin <name>\n\t\tbakes a new plugin folder in the path supplied\n\t\tor in current directory if no path is specified.");
        $this->out("\n\tbake_utf8 db_config\n\t\tbakes a database.php file in config directory.");
        $this->out("\n\tbake_utf8 model\n\t\tbakes a model. run 'bake model help' for more info");
        $this->out("\n\tbake_utf8 view\n\t\tbakes views. run 'bake view help' for more info");
        $this->out("\n\tbake_utf8 controller\n\t\tbakes a controller. run 'bake controller help' for more info");
        $this->out("\n\tbake_utf8 fixture\n\t\tbakes fixtures. run 'bake fixture help' for more info.");
        $this->out("\n\tbake_utf8 test\n\t\tbakes unit tests. run 'bake test help' for more info.");
        $this->out();

    }
}
