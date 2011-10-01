<?php
/**
 * Arquivo que executa o comando 'bake_utf8 plugin_utf8'
 *
 * Compatível com PHP 4 e 5
 *
 * @filesource
 * @author           Pedro Elsner <pedro.elsner@gmail.com>
 * @since       v 1.0
 */


/**
 * Plugin Utf8 Task
 *
 * @use         BakeUtf8Task
 * @package        bake_utf8
 * @subpackage     bake_utf8.plugin_utf8_task
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */
class PluginUtf8Task extends Shell
{
    
/**
 * Carrega todas as taks
 *
 * @var array
 * @access public
 */
    var $tasks = array(
        'ModelUtf8',
        'ControllerUtf8',
        'ViewUtf8'
    );
    
/**
 * Diret�rio
 *
 * @var string
 * @access public
 */
    var $path = null;
    
    
/**
 * Initialize
 *
 * @access public
 */
    function initialize()
    {
        $this->path = APP . 'plugins' . DS;
    }
    
    
/**
 * Execute
 *
 * @access public
 */
    function execute()
    {
        if (empty($this->params['skel']))
        {
            $this->params['skel'] = '';
            if (is_dir(CAKE_CORE_INCLUDE_PATH . DS . CAKE . 'console' . DS . 'templates' . DS . 'skel') === true)
            {
                $this->params['skel'] = CAKE_CORE_INCLUDE_PATH . DS . CAKE . 'console' . DS . 'templates' . DS . 'skel';
            }
        }
        $plugin = null;

        if (isset($this->args[0]))
        {
            $plugin = Inflector::camelize($this->args[0]);
            $pluginPath = $this->_pluginPath($plugin);
            $this->Dispatch->shiftArgs();
            if (is_dir($pluginPath))
            {
                $this->out(sprintf(__('Plugin: %s', true), $plugin));
                $this->out(sprintf(__('Path: %s', true), $pluginPath));
            }
            elseif (isset($this->args[0]))
            {
                $this->err(sprintf(__('%s in path %s not found.', true), $plugin, $pluginPath));
                $this->_stop();
            }
            else
            {
                $this->__interactive($plugin);
            }
        }
        else
        {
            return $this->__interactive();
        }

        if (isset($this->args[0]))
        {
            $task = Inflector::classify($this->args[0]);
            $this->Dispatch->shiftArgs();
            if (in_array($task, $this->tasks))
            {
                $this->{$task}->plugin = $plugin;
                $this->{$task}->path = $pluginPath . Inflector::underscore(Inflector::pluralize($task)) . DS;

                if (!is_dir($this->{$task}->path))
                {
                    $this->err(sprintf(__("%s directory could not be found.\nBe sure you have created %s", true), $task, $this->{$task}->path));
                }
                $this->{$task}->loadTasks();
                return $this->{$task}->execute();
            }
        }
    }
    
    
/**
 * Interactive
 *
 * @access private
 */
    function __interactive($plugin = null) {
        while ($plugin === null) {
            $plugin = $this->in(__('Enter the name of the plugin in CamelCase format', true));
        }

        if (!$this->bake($plugin)) {
            $this->err(sprintf(__("An error occured trying to bake: %s in %s", true), $plugin, $this->path . Inflector::underscore($pluginPath)));
        }
    }
    
    
/**
 * Bake
 *    
 * @param string $plugin
 * @return boolean
 * @access public
 */
    function bake($plugin)
    {
        $pluginPath = Inflector::underscore($plugin);

        $pathOptions = App::path('plugins');
        if (count($pathOptions) > 1)
        {
            $this->findPath($pathOptions);
        }
        $this->hr();
        $this->out(sprintf(__("Plugin Name: %s", true),  $plugin));
        $this->out(sprintf(__("Plugin Directory: %s", true), $this->path . $pluginPath));
        $this->hr();

        $looksGood = $this->in(__('Look okay?', true), array('y', 'n', 'q'), 'y');

        if (strtolower($looksGood) == 'y')
        {
            $verbose = $this->in(__('Do you want verbose output?', true), array('y', 'n'), 'n');

            $Folder =& new Folder($this->path . $pluginPath);
            $directories = array(
                'config' . DS . 'schema',
                'models' . DS . 'behaviors',
                'models' . DS . 'datasources',
                'controllers' . DS . 'components',
                'libs',
                'views' . DS . 'helpers',
                'tests' . DS . 'cases' . DS . 'components',
                'tests' . DS . 'cases' . DS . 'helpers',
                'tests' . DS . 'cases' . DS . 'behaviors',
                'tests' . DS . 'cases' . DS . 'controllers',
                'tests' . DS . 'cases' . DS . 'models',
                'tests' . DS . 'groups',
                'tests' . DS . 'fixtures',
                'vendors',
                'vendors' . DS . 'shells' . DS . 'tasks',
                'webroot'
            );

            foreach ($directories as $directory)
            {
                $dirPath = $this->path . $pluginPath . DS . $directory;
                $Folder->create($dirPath);
                $File =& new File($dirPath . DS . 'empty', true);
            }

            if (strtolower($verbose) == 'y')
            {
                foreach ($Folder->messages() as $message)
                {
                    $this->out($message);
                }
            }

            $errors = $Folder->errors();
            if (!empty($errors))
            {
                return false;
            }

            $controllerFileName = $pluginPath . '_app_controller.php';

            $out = "<?php\n\n";
            $out .= "class {$plugin}AppController extends AppController {\n\n";
            $out .= "}\n\n";
            $out .= "?>";
            $this->createFile($this->path . $pluginPath. DS . $controllerFileName, $out);

            $modelFileName = $pluginPath . '_app_model.php';

            $out = "<?php\n\n";
            $out .= "class {$plugin}AppModel extends AppModel {\n\n";
            $out .= "}\n\n";
            $out .= "?>";
            $this->createFile($this->path . $pluginPath . DS . $modelFileName, $out);

            $this->hr();
            $this->out(sprintf(__("Created: %s in %s", true), $plugin, $this->path . $pluginPath));
            $this->hr();
        }

        return true;
    }
    
    
/**
 * Find Path
 *    
 * @param array $pathOptions
 * @access public
 */
    function findPath($pathOptions)
    {
        $valid = false;
        $max = count($pathOptions);
        while (!$valid)
        {
            foreach ($pathOptions as $i => $option)
            {
                $this->out($i + 1 .'. ' . $option);
            }
            $prompt = __('Choose a plugin path from the paths above.', true);
            $choice = $this->in($prompt);
            if (intval($choice) > 0 && intval($choice) <= $max)
            {
                $valid = true;
            }
        }
        $this->path = $pathOptions[$choice - 1];
    }
    
    
/**
 * Help
 *
 * Responde o comando 'bake_utf8 plugin_utf8 help'
 *
 * @access public
 */
    function help() {
        $this->hr();
        $this->out("Usage: cake bake_utf8 plugin_utf8 <arg1> <arg2>...");
        $this->hr();
        $this->out('Commands:');
        $this->out();
        $this->out("plugin_utf8 <name>");
        $this->out("\tbakes plugin directory structure");
        $this->out();
        $this->out("plugin_utf8 <name> model_utf8");
        $this->out("\tbakes model. Run 'cake bake model help' for more info.");
        $this->out();
        $this->out("plugin_utf8 <name> controller_utf8");
        $this->out("\tbakes controller. Run 'cake bake controller help' for more info.");
        $this->out();
        $this->out("plugin_utf8 <name> view_utf8");
        $this->out("\tbakes view. Run 'cake bake view help' for more info.");
        $this->out();
        $this->_stop();
    }
}
