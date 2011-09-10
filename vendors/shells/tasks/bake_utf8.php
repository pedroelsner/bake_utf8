<?php
/**
 * Arquivo que carrega as taks do comando 'bake_utf8'
 *
 * Compatível com PHP 4 e 5
 *
 * @filesource
 * @author      Pedro Elsner <pedro.elsner@gmail.com>
 * @since       v 1.0
 */

 
/**
 * Bake Utf8 Shell
 *
 * @use         Shell
 * @package     bake_utf8
 * @subpackage  bake_utf8.bake_utf8_task
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */
class BakeUtf8Task extends Shell
{
    
/**
 * @var boolean
 * @access public
 */
    var $plugin = null;
    
/**
 * @var object
 * @access public
 */
    var $connection = null;

/**
 * @var boolean
 * @access public
 */
    var $interactive = false;
    
    
/**
 * Get Path
 *
 * @return string
 * @access public
 */
    function getPath()
    {
        $path = $this->path;
        if (isset($this->plugin))
        {
            $name = substr($this->name, 0, strlen($this->name) - 4);
            $path = $this->_pluginPath($this->plugin) . Inflector::pluralize(Inflector::underscore($name)) . DS;
        }
        return $path;
    }
    
    
/**
 * Remove BOM
 *
 * Retira o cabe�alho (BOM) do arquivo
 *
 * @param string $path Caminho do arquivo
 * @return boolean
 * @access public
 */
    function removeBOM($path)
    {
        
        $content = '';
        $first = false;
        $fh = fopen($path, 'r');
        while($part = fread($fh, 1024))
        {
            if(!$first)
            {
                if(preg_match('/^\xEF\xBB\xBF/', $part))
                {
                    $newcontent = preg_replace('/^\xEF\xBB\xBF/', "", $part);
                }
                else
                {
                    fclose($fh);
                    return false;
                }
                $first = true;
            }
            else
            {
                $newcontent .= $part;
            }
        }
        fclose($fh);
        
        $fh = fopen($path, 'w');
        fwrite($fh, $newcontent);
        fclose($fh);
        
        return true;
    }
    
    
/**
 * Convert To UTF8
 * 
 * Converte conteudo do arquivo para UTF8
 *
 * @param string $path Caminho do arquivo
 * @access public
 */
    function convertToUTF8($path) {
        
        $content = '';
        
        //! Pega conteudo do arquivo
        $fh = fopen($path, 'r');
        while($part = fread($fh, 1024))
        {
            $content .= $part;
        }
        fclose($fh);
        
        //! Verifica e convert conteudo
        //if(!mb_check_encoding($content, 'UTF-8')) {
            $content = utf8_encode($content); 
            //Ou se preferir: $content = mb_convert_encoding($content, 'UTF-8'); 
        //}
        
        //! Grava conte�do no arquivo
        $fh = fopen($path, 'w');
        fwrite($fh, $content);
        fclose($fh);
        
    }
    
    
/**
 * Load Tasks
 *
 * @access public
 */
    function loadTasks()
    {
        parent::loadTasks();
        $task = Inflector::classify($this->command);
        if (isset($this->{$task}) && !in_array($task, array('Project', 'DbConfig')))
        {
            if (isset($this->params['connection']))
            {
                $this->{$task}->connection = $this->params['connection'];
            }
            foreach($this->args as $i => $arg)
            {
                if (strpos($arg, '.'))
                {
                    list($this->params['plugin'], $this->args[$i]) = pluginSplit($arg);
                    break;
                }
            }
            if (isset($this->params['plugin']))
            {
                $this->{$task}->plugin = $this->params['plugin'];
            }
        }
    }
    
}
