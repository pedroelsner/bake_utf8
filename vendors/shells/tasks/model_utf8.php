<?php
/**
 * Arquivo que executa o comando 'bake_utf8 model_utf8'
 *
 * Compatível com PHP 4 e 5
 *
 * Licenciado pela Creative Commons 3.0
 *
 * @filesource
 * @copyright   Copyright 2011, Pedro Elsner (http://pedroelsner.com/)
 * @author	   	Pedro Elsner <pedro.elsner@gmail.com>
 * @license    	Creative Commons 3.0 (http://creativecommons.org/licenses/by/3.0/br/)
 * @since       v 1.0
 */

include_once dirname(__FILE__) . DS . 'bake_utf8.php';


/**
 * Model Utf8 Task
 *
 * @use         BakeUtf8Task
 * @package    	bake_utf8
 * @subpackage  bake_utf8.model_utf8_task
 * @link        http://www.github.com/pedroelsner/bake_utf8
 */
class ModelUtf8Task extends BakeUtf8Task
{
	
	/**
	 * Diretório
	 *
	 * @var string
	 * @access public
	 */
	var $path = MODELS;
	
	/**
	 * Carrega todas as taks
	 *
	 * @var array
	 * @access public
	 */
	var $tasks = array(
		'DbConfig',
		'FixtureUtf8',
		'TestUtf8',
		'Template'
	);
	
	/**
	 * @var array
	 * @access public
	 */
	var $skipTables = array('i18n');
	
	/**
	 * @var array
	 * @access public
	 */
	var $_tables = array();
	
	/**
	 * @var array
	 * @access public
	 */
	var $_validations = array();
	
	
	/**
	 * Execute
	 *
	 * @access public
	 */
	function execute()
	{
		App::import('Model', 'Model', false);

		if (empty($this->args))
		{
			$this->__interactive();
		}

		if (!empty($this->args[0]))
		{
			$this->interactive = false;
			if (!isset($this->connection))
			{
				$this->connection = 'default';
			}
			if (strtolower($this->args[0]) == 'all')
			{
				return $this->all();
			}
			$model = $this->_modelName($this->args[0]);
			$object = $this->_getModelObject($model);
			if ($this->bake($object, false))
			{
				if ($this->_checkUnitTest())
				{
					$this->bakeFixture($model);
					$this->bakeTest($model);
				}
			}
		}
	}
	
	
	/**
	 * All
	 *
	 * Responde ao comando 'bake_utf8 model_utf8 all'
	 *
	 * @access public
	 */
	function all() {
		$this->listAll($this->connection, false);
		$unitTestExists = $this->_checkUnitTest();
		foreach ($this->_tables as $table)
		{
			if (in_array($table, $this->skipTables))
			{
				continue;
			}
			$modelClass = Inflector::classify($table);
			$this->out(sprintf(__('Baking %s', true), $modelClass));
			$object = $this->_getModelObject($modelClass);
			if ($this->bake($object, false) && $unitTestExists)
			{
				$this->bakeFixture($modelClass);
				$this->bakeTest($modelClass);
			}
		}
	}

	
	/**
	 * Get Model Object
	 *
	 * @param string $className
	 * @param string $table
	 * @return object
	 * @access protected
	 */
	function &_getModelObject($className, $table = null)
	{
		if (!$table)
		{
			$table = Inflector::tableize($className);
		}
		$object =& new Model(array('name' => $className, 'table' => $table, 'ds' => $this->connection));
		return $object;
	}

	
	/**
	 * In Options
	 *
	 * @param array $options
	 * @param string $prompt
	 * @param string $default
	 * @return int
	 * @access public
	 */
	function inOptions($options, $prompt = null, $default = null) {
		$valid = false;
		$max = count($options);
		while (!$valid) {
			foreach ($options as $i => $option) {
				$this->out($i + 1 .'. ' . $option);
			}
			if (empty($prompt)) {
				$prompt = __('Make a selection from the choices above', true);
			}
			$choice = $this->in($prompt, null, $default);
			if (intval($choice) > 0 && intval($choice) <= $max) {
				$valid = true;
			}
		}
		return $choice - 1;
	}
	
	
	/**
	 * Interactive
	 *
	 * @return 
	 * @access private
	 */
	function __interactive()
	{
		$this->hr();
		$this->out(sprintf("Bake Model\nPath: %s", $this->path));
		$this->hr();
		$this->interactive = true;

		$primaryKey = 'id';
		$validate = $associations = array();

		if (empty($this->connection))
		{
			$this->connection = $this->DbConfig->getConfig();
		}
		$currentModelName = $this->getName();
		$useTable = $this->getTable($currentModelName);
		$db =& ConnectionManager::getDataSource($this->connection);
		$fullTableName = $db->fullTableName($useTable);

		if (in_array($useTable, $this->_tables))
		{
			$tempModel = new Model(array('name' => $currentModelName, 'table' => $useTable, 'ds' => $this->connection));
			$fields = $tempModel->schema(true);
			if (!array_key_exists('id', $fields))
			{
				$primaryKey = $this->findPrimaryKey($fields);
			}
		}
		else
		{
			$this->err(sprintf(__('Table %s does not exist, cannot bake a model without a table.', true), $useTable));
			$this->_stop();
			return false;
		}
		$displayField = $tempModel->hasField(array('name', 'title'));
		if (!$displayField)
		{
			$displayField = $this->findDisplayField($tempModel->schema());
		}

		$prompt = __("Would you like to supply validation criteria \nfor the fields in your model?", true);
		$wannaDoValidation = $this->in($prompt, array('y','n'), 'y');
		if (array_search($useTable, $this->_tables) !== false && strtolower($wannaDoValidation) == 'y')
		{
			$validate = $this->doValidation($tempModel);
		}

		$prompt = __("Would you like to define model associations\n(hasMany, hasOne, belongsTo, etc.)?", true);
		$wannaDoAssoc = $this->in($prompt, array('y','n'), 'y');
		if (strtolower($wannaDoAssoc) == 'y')
		{
			$associations = $this->doAssociations($tempModel);
		}

		$this->out();
		$this->hr();
		$this->out(__('The following Model will be created:', true));
		$this->hr();
		$this->out("Name:       " . $currentModelName);

		if ($this->connection !== 'default')
		{
			$this->out(sprintf(__("DB Config:  %s", true), $this->connection));
		}
		if ($fullTableName !== Inflector::tableize($currentModelName))
		{
			$this->out(sprintf(__("DB Table:   %s", true), $fullTableName));
		}
		if ($primaryKey != 'id')
		{
			$this->out(sprintf(__("Primary Key: %s", true), $primaryKey));
		}
		if (!empty($validate))
		{
			$this->out(sprintf(__("Validation: %s", true), print_r($validate, true)));
		}
		if (!empty($associations))
		{
			$this->out(__("Associations:", true));
			$assocKeys = array('belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany');
			foreach ($assocKeys as $assocKey)
			{
				$this->_printAssociation($currentModelName, $assocKey, $associations);
			}
		}

		$this->hr();
		$looksGood = $this->in(__('Look okay?', true), array('y','n'), 'y');

		if (strtolower($looksGood) == 'y')
		{
			$vars = compact('associations', 'validate', 'primaryKey', 'useTable', 'displayField');
			$vars['useDbConfig'] = $this->connection;
			if ($this->bake($currentModelName, $vars))
			{
				if ($this->_checkUnitTest())
				{
					$this->bakeFixture($currentModelName, $useTable);
					$this->bakeTest($currentModelName, $useTable, $associations);
				}
			}
		}
		else
		{
			return false;
		}
	}

	
	/**
	 * Print Association
	 *
	 * @param string $modelName
	 * @param string $type
	 * @param array $associations
	 * @access protected
	 */
	function _printAssociation($modelName, $type, $associations) {
		if (!empty($associations[$type])) {
			for ($i = 0; $i < count($associations[$type]); $i++) {
				$out = "\t" . $modelName . ' ' . $type . ' ' . $associations[$type][$i]['alias'];
				$this->out($out);
			}
		}
	}

	
	/**
	 * Find Primary Key
	 *	
	 * @param array $fields
	 * @return string
	 * @access public
	 */
	function findPrimaryKey($fields) {
		foreach ($fields as $name => $field) {
			if (isset($field['key']) && $field['key'] == 'primary') {
				break;
			}
		}
		return $this->in(__('What is the primaryKey?', true), null, $name);
	}
	
	
	/**
	 * Find Display Field
	 *	
	 * @param array $fields
	 * @return string
	 * @access public
	 */
	function findDisplayField($fields)
	{
		$fieldNames = array_keys($fields);
		$prompt = __("A displayField could not be automatically detected\nwould you like to choose one?", true);
		$continue = $this->in($prompt, array('y', 'n'));
		if (strtolower($continue) == 'n')
		{
			return false;
		}
		$prompt = __('Choose a field from the options above:', true);
		$choice = $this->inOptions($fieldNames, $prompt);
		return $fieldNames[$choice];
	}

	
	/**
	 * Do Validation
	 *	
	 * @param objetc $model
	 * @return boolean
	 * @access public
	 */
	function doValidation(&$model)
	{
		if (!is_object($model))
		{
			return false;
		}
		$fields = $model->schema();

		if (empty($fields))
		{
			return false;
		}
		$validate = array();
		$this->initValidations();
		foreach ($fields as $fieldName => $field)
		{
			$validation = $this->fieldValidation($fieldName, $field, $model->primaryKey);
			if (!empty($validation))
			{
				$validate[$fieldName] = $validation;
			}
		}
		return $validate;
	}

	
	/**
	 * Init Validations
	 *	
	 * @access public
	 */
	function initValidations()
	{
		$options = $choices = array();
		if (class_exists('Validation'))
		{
			$parent = get_class_methods(get_parent_class('Validation'));
			$options = get_class_methods('Validation');
			$options = array_diff($options, $parent);
		}
		sort($options);
		$default = 1;
		foreach ($options as $key => $option)
		{
			if ($option{0} != '_' && strtolower($option) != 'getinstance')
			{
				$choices[$default] = strtolower($option);
				$default++;
			}
		}
		$this->_validations = $choices;
		return $choices;
	}
	
	
	/**
	 * Field Validation
	 *	
	 * @param string $fieldName
	 * @param array $metaData
	 * @param string $primaryKey
	 * @return array
	 * @access public
	 */
	function fieldValidation($fieldName, $metaData, $primaryKey = 'id')
	{
		$defaultChoice = count($this->_validations);
		$validate = $alreadyChosen = array();

		$anotherValidator = 'y';
		while ($anotherValidator == 'y')
		{
			if ($this->interactive)
			{
				$this->out();
				$this->out(sprintf(__('Field: %s', true), $fieldName));
				$this->out(sprintf(__('Type: %s', true), $metaData['type']));
				$this->hr();
				$this->out(__('Please select one of the following validation options:', true));
				$this->hr();
			}

			$prompt = '';
			for ($i = 1; $i < $defaultChoice; $i++)
			{
				$prompt .= $i . ' - ' . $this->_validations[$i] . "\n";
			}
			$prompt .=  sprintf(__("%s - Do not do any validation on this field.\n", true), $defaultChoice);
			$prompt .= __("... or enter in a valid regex validation string.\n", true);

			$methods = array_flip($this->_validations);
			$guess = $defaultChoice;
			if ($metaData['null'] != 1 && !in_array($fieldName, array($primaryKey, 'created', 'modified', 'updated')))
			{
				if ($fieldName == 'email')
				{
					$guess = $methods['email'];
				}
				elseif ($metaData['type'] == 'string')
				{
					$guess = $methods['notempty'];
				}
				elseif ($metaData['type'] == 'integer')
				{
					$guess = $methods['numeric'];
				}
				elseif ($metaData['type'] == 'boolean')
				{
					$guess = $methods['boolean'];
				}
				elseif ($metaData['type'] == 'date')
				{
					$guess = $methods['date'];
				}
				elseif ($metaData['type'] == 'time')
				{
					$guess = $methods['time'];
				}
			}

			if ($this->interactive === true)
			{
				$choice = $this->in($prompt, null, $guess);
				if (in_array($choice, $alreadyChosen))
				{
					$this->out(__("You have already chosen that validation rule,\nplease choose again", true));
					continue;
				}
				if (!isset($this->_validations[$choice]) && is_numeric($choice))
				{
					$this->out(__('Please make a valid selection.', true));
					continue;
				}
				$alreadyChosen[] = $choice;
			}
			else
			{
				$choice = $guess;
			}

			if (isset($this->_validations[$choice]))
			{
				$validatorName = $this->_validations[$choice];
			}
			else
			{
				$validatorName = Inflector::slug($choice);
			}

			if ($choice != $defaultChoice)
			{
				if (is_numeric($choice) && isset($this->_validations[$choice]))
				{
					$validate[$validatorName] = $this->_validations[$choice];
				}
				else
				{
					$validate[$validatorName] = $choice;
				}
			}
			if ($this->interactive == true && $choice != $defaultChoice)
			{
				$anotherValidator = $this->in(__('Would you like to add another validation rule?', true), array('y', 'n'), 'n');
			}
			else
			{
				$anotherValidator = 'n';
			}
		}
		return $validate;
	}
	
	
	/**
	 * Do Associations
	 *	
	 * @param object $model
	 * @return boolean
	 * @access public
	 */
	function doAssociations(&$model)
	{
		if (!is_object($model))
		{
			return false;
		}
		if ($this->interactive === true)
		{
			$this->out(__('One moment while the associations are detected.', true));
		}

		$fields = $model->schema(true);
		if (empty($fields))
		{
			return false;
		}

		if (empty($this->_tables))
		{
			$this->_tables = $this->getAllTables();
		}

		$associations = array(
			'belongsTo' => array(), 'hasMany' => array(), 'hasOne'=> array(), 'hasAndBelongsToMany' => array()
		);
		$possibleKeys = array();

		$associations = $this->findBelongsTo($model, $associations);
		$associations = $this->findHasOneAndMany($model, $associations);
		$associations = $this->findHasAndBelongsToMany($model, $associations);

		if ($this->interactive !== true)
		{
			unset($associations['hasOne']);
		}

		if ($this->interactive === true)
		{
			$this->hr();
			if (empty($associations))
			{
				$this->out(__('None found.', true));
			}
			else
			{
				$this->out(__('Please confirm the following associations:', true));
				$this->hr();
				$associations = $this->confirmAssociations($model, $associations);
			}
			$associations = $this->doMoreAssociations($model, $associations);
		}
		return $associations;
	}
	
	
	/**
	 * Find Belogns To
	 *	
	 * @param object $model
	 * @param array $associations
	 * @return array
	 * @access public
	 */
	function findBelongsTo(&$model, $associations)
	{
		$fields = $model->schema(true);
		foreach ($fields as $fieldName => $field)
		{
			$offset = strpos($fieldName, '_id');
			if ($fieldName != $model->primaryKey && $fieldName != 'parent_id' && $offset !== false)
			{
				$tmpModelName = $this->_modelNameFromKey($fieldName);
				$associations['belongsTo'][] = array(
					'alias' => $tmpModelName,
					'className' => $tmpModelName,
					'foreignKey' => $fieldName,
				);
			}
			elseif ($fieldName == 'parent_id')
			{
				$associations['belongsTo'][] = array(
					'alias' => 'Parent' . $model->name,
					'className' => $model->name,
					'foreignKey' => $fieldName,
				);
			}
		}
		return $associations;
	}

	
	/**
	 * Find Has One And Many
	 *	
	 * @param object $model
	 * @param array $associations
	 * @return array
	 * @access public
	 */
	function findHasOneAndMany(&$model, $associations)
	{
		$foreignKey = $this->_modelKey($model->name);
		foreach ($this->_tables as $otherTable)
		{
			$tempOtherModel = $this->_getModelObject($this->_modelName($otherTable), $otherTable);
			$modelFieldsTemp = $tempOtherModel->schema(true);

			$pattern = '/_' . preg_quote($model->table, '/') . '|' . preg_quote($model->table, '/') . '_/';
			$possibleJoinTable = preg_match($pattern , $otherTable);
			if ($possibleJoinTable == true)
			{
				continue;
			}
			foreach ($modelFieldsTemp as $fieldName => $field)
			{
				$assoc = false;
				if ($fieldName != $model->primaryKey && $fieldName == $foreignKey)
				{
					$assoc = array(
						'alias' => $tempOtherModel->name,
						'className' => $tempOtherModel->name,
						'foreignKey' => $fieldName
					);
				}
				elseif ($otherTable == $model->table && $fieldName == 'parent_id')
				{
					$assoc = array(
						'alias' => 'Child' . $model->name,
						'className' => $model->name,
						'foreignKey' => $fieldName
					);
				}
				if ($assoc)
				{
					$associations['hasOne'][] = $assoc;
					$associations['hasMany'][] = $assoc;
				}

			}
		}
		return $associations;
	}
	
	
	/**
	 * Confirm Associations
	 *	
	 * @param object $model
	 * @param array $associations
	 * @return array
	 * @access public
	 */
	function confirmAssociations(&$model, $associations)
	{
		foreach ($associations as $type => $settings)
		{
			if (!empty($associations[$type]))
			{
				$count = count($associations[$type]);
				$response = 'y';
				foreach ($associations[$type] as $i => $assoc)
				{
					$prompt = "{$model->name} {$type} {$assoc['alias']}?";
					$response = $this->in($prompt, array('y','n'), 'y');

					if ('n' == strtolower($response))
					{
						unset($associations[$type][$i]);
					}
					elseif ($type == 'hasMany')
					{
						unset($associations['hasOne'][$i]);
					}
				}
				$associations[$type] = array_merge($associations[$type]);
			}
		}
		return $associations;
	}
	
	
	/**
	 * Do More Associations
	 *	
	 * @param object $model
	 * @param array $associations
	 * @return array
	 * @access public
	 */
	function doMoreAssociations($model, $associations)
	{
		$prompt = __('Would you like to define some additional model associations?', true);
		$wannaDoMoreAssoc = $this->in($prompt, array('y','n'), 'n');
		$possibleKeys = $this->_generatePossibleKeys();
		while (strtolower($wannaDoMoreAssoc) == 'y')
		{
			$assocs = array('belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany');
			$this->out(__('What is the association type?', true));
			$assocType = intval($this->inOptions($assocs, __('Enter a number',true)));

			$this->out(__("For the following options be very careful to match your setup exactly.\nAny spelling mistakes will cause errors.", true));
			$this->hr();

			$alias = $this->in(__('What is the alias for this association?', true));
			$className = $this->in(sprintf(__('What className will %s use?', true), $alias), null, $alias );
			$suggestedForeignKey = null;

			if ($assocType == 0)
			{
				$showKeys = $possibleKeys[$model->table];
				$suggestedForeignKey = $this->_modelKey($alias);
			}
			else
			{
				$otherTable = Inflector::tableize($className);
				if (in_array($otherTable, $this->_tables))
				{
					if ($assocType < 3)
					{
						$showKeys = $possibleKeys[$otherTable];
					}
					else
					{
						$showKeys = null;
					}
				}
				else
				{
					$otherTable = $this->in(__('What is the table for this model?', true));
					$showKeys = $possibleKeys[$otherTable];
				}
				$suggestedForeignKey = $this->_modelKey($model->name);
			}
			if (!empty($showKeys))
			{
				$this->out(__('A helpful List of possible keys', true));
				$foreignKey = $this->inOptions($showKeys, __('What is the foreignKey?', true));
				$foreignKey = $showKeys[intval($foreignKey)];
			}
			if (!isset($foreignKey))
			{
				$foreignKey = $this->in(__('What is the foreignKey? Specify your own.', true), null, $suggestedForeignKey);
			}
			if ($assocType == 3)
			{
				$associationForeignKey = $this->in(__('What is the associationForeignKey?', true), null, $this->_modelKey($model->name));
				$joinTable = $this->in(__('What is the joinTable?', true));
			}
			$associations[$assocs[$assocType]] = array_values((array)$associations[$assocs[$assocType]]);
			$count = count($associations[$assocs[$assocType]]);
			$i = ($count > 0) ? $count : 0;
			$associations[$assocs[$assocType]][$i]['alias'] = $alias;
			$associations[$assocs[$assocType]][$i]['className'] = $className;
			$associations[$assocs[$assocType]][$i]['foreignKey'] = $foreignKey;
			if ($assocType == 3)
			{
				$associations[$assocs[$assocType]][$i]['associationForeignKey'] = $associationForeignKey;
				$associations[$assocs[$assocType]][$i]['joinTable'] = $joinTable;
			}
			$wannaDoMoreAssoc = $this->in(__('Define another association?', true), array('y','n'), 'y');
		}
		return $associations;
	}
	
	
	/**
	 * Generate Possible Keys
	 *	
	 * @return array
	 * @access protected
	 */
	function _generatePossibleKeys()
	{
		$possible = array();
		foreach ($this->_tables as $otherTable)
		{
			$tempOtherModel = & new Model(array('table' => $otherTable, 'ds' => $this->connection));
			$modelFieldsTemp = $tempOtherModel->schema(true);
			foreach ($modelFieldsTemp as $fieldName => $field)
			{
				if ($field['type'] == 'integer' || $field['type'] == 'string')
				{
					$possible[$otherTable][] = $fieldName;
				}
			}
		}
		return $possible;
	}

	
	/**
	 * Bake
	 *	
	 * @param string $name
	 * @param array $data
	 * @return string
	 * @access public
	 */
	function bake($name, $data = array())
	{
		if (is_object($name))
		{
			if ($data == false)
			{
				$data = $associations = array();
				$data['associations'] = $this->doAssociations($name, $associations);
				$data['validate'] = $this->doValidation($name);
			}
			$data['primaryKey'] = $name->primaryKey;
			$data['useTable'] = $name->table;
			$data['useDbConfig'] = $name->useDbConfig;
			$data['name'] = $name = $name->name;
		}
		else
		{
			$data['name'] = $name;
		}
		$defaults = array('associations' => array(), 'validate' => array(), 'primaryKey' => 'id',
			'useTable' => null, 'useDbConfig' => 'default', 'displayField' => null);
		$data = array_merge($defaults, $data);

		$this->Template->set($data);
		$this->Template->set('plugin', Inflector::camelize($this->plugin));
		$out = $this->Template->generate('classes', 'model');

		$path = $this->getPath();
		$filename = $path . Inflector::underscore($name) . '.php';
		$this->out("\nBaking model class for $name...");
		$this->createFile($filename, $out);
		
		/**
		 * Convert para UTF8
		 */
		$this->out(sprintf(__('Converting file to UTF-8...', true)));
		$this->convertToUTF8($filename);
		$this->removeBOM($filename);
		
		
		ClassRegistry::flush();
		return $out;
	}
	
	
	/**
	 * Bake Test
	 *	
	 * @param string $className
	 * @return
	 * @access public
	 */
	function bakeTest($className) {
		$this->TestUtf8->interactive = $this->interactive;
		$this->TestUtf8->plugin = $this->plugin;
		$this->TestUtf8->connection = $this->connection;
		return $this->TestUtf8->bake('Model', $className);
	}

	
	/**
	 * List All
	 *
	 * @param string $useDbConfig
	 * @access public
	 */
	function listAll($useDbConfig = null) {
		$this->_tables = $this->getAllTables($useDbConfig);

		if ($this->interactive === true) {
			$this->out(__('Possible Models based on your current database:', true));
			$this->_modelNames = array();
			$count = count($this->_tables);
			for ($i = 0; $i < $count; $i++) {
				$this->_modelNames[] = $this->_modelName($this->_tables[$i]);
				$this->out($i + 1 . ". " . $this->_modelNames[$i]);
			}
		}
		return $this->_tables;
	}

	
	/**
	 * Get Table
	 *
	 * @param string $modelName
	 * @param string #useDbConfig
	 * @return string
	 * @access public
	 */
	function getTable($modelName, $useDbConfig = null)
	{
		if (!isset($useDbConfig))
		{
			$useDbConfig = $this->connection;
		}
		App::import('Model', 'ConnectionManager', false);

		$db =& ConnectionManager::getDataSource($useDbConfig);
		$useTable = Inflector::tableize($modelName);
		$fullTableName = $db->fullTableName($useTable, false);
		$tableIsGood = false;

		if (array_search($useTable, $this->_tables) === false)
		{
			$this->out();
			$this->out(sprintf(__("Given your model named '%s',\nCake would expect a database table named '%s'", true), $modelName, $fullTableName));
			$tableIsGood = $this->in(__('Do you want to use this table?', true), array('y','n'), 'y');
		}
		if (strtolower($tableIsGood) == 'n')
		{
			$useTable = $this->in(__('What is the name of the table?', true));
		}
		return $useTable;
	}
	
	
	/**
	 * Get All Tables
	 *
	 * @param string $useDbConfig
	 * @return array
	 * @access public
	 */
	function getAllTables($useDbConfig = null)
	{
		if (!isset($useDbConfig))
		{
			$useDbConfig = $this->connection;
		}
		App::import('Model', 'ConnectionManager', false);

		$tables = array();
		$db =& ConnectionManager::getDataSource($useDbConfig);
		$db->cacheSources = false;
		$usePrefix = empty($db->config['prefix']) ? '' : $db->config['prefix'];
		if ($usePrefix)
		{
			foreach ($db->listSources() as $table)
			{
				if (!strncmp($table, $usePrefix, strlen($usePrefix)))
				{
					$tables[] = substr($table, strlen($usePrefix));
				}
			}
		}
		else
		{
			$tables = $db->listSources();
		}
		if (empty($tables))
		{
			$this->err(__('Your database does not have any tables.', true));
			$this->_stop();
		}
		return $tables;
	}
	
	
	/**
	 * Get Name
	 *
	 * @param string $useDbConfig
	 * @return string
	 * @access public
	 */
	function getName($useDbConfig = null)
	{
		$this->listAll($useDbConfig);

		$enteredModel = '';

		while ($enteredModel == '')
		{
			$enteredModel = $this->in(__("Enter a number from the list above,\ntype in the name of another model, or 'q' to exit", true), null, 'q');

			if ($enteredModel === 'q')
			{
				$this->out(__("Exit", true));
				$this->_stop();
			}

			if ($enteredModel == '' || intval($enteredModel) > count($this->_modelNames))
			{
				$this->err(__("The model name you supplied was empty,\nor the number you selected was not an option. Please try again.", true));
				$enteredModel = '';
			}
		}
		if (intval($enteredModel) > 0 && intval($enteredModel) <= count($this->_modelNames))
		{
			$currentModelName = $this->_modelNames[intval($enteredModel) - 1];
		}
		else
		{
			$currentModelName = $enteredModel;
		}
		return $currentModelName;
	}
	
	
	/**
	 * Help
	 *
	 * Responde o comando 'bake_utf8 model_utf8 help'
	 *
	 * @access public
	 */
	function help() {
		$this->hr();
		$this->out("Usage: cake bake model <arg1>");
		$this->hr();
		$this->out('Arguments:');
		$this->out();
		$this->out("<name>");
		$this->out("\tName of the model to bake. Can use Plugin.name");
		$this->out("\tas a shortcut for plugin baking.");
		$this->out();
		$this->out('Commands:');
		$this->out();
		$this->out("model");
		$this->out("\tbakes model in interactive mode.");
		$this->out();
		$this->out("model <name>");
		$this->out("\tbakes model file with no associations or validation");
		$this->out();
		$this->out("model all");
		$this->out("\tbakes all model files with associations and validation");
		$this->out();
		$this->_stop();
	}

	
	/**
	 * Bake Fixture
	 *
	 * @param string $className
	 * @param string $useTable
	 * @access public
	 */
	function bakeFixture($className, $useTable = null) {
		$this->FixtureUtf8->interactive = $this->interactive;
		$this->FixtureUtf8->connection = $this->connection;
		$this->FixtureUtf8->plugin = $this->plugin;
		$this->FixtureUtf8->bake($className, $useTable);
	}
	
}
