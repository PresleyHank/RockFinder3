<?php namespace ProcessWire;
/**
 * RockFinder3 Master module
 *
 * @author Bernhard Baumrock, 22.05.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
require("Column.php");
require("FinderData.php");
class RockFinder3Master extends WireData implements Module, ConfigurableModule {

  /** @var WireArray */
  public $finders;

  /** @var array */
  public $baseColumns;
  
  /** @var WireArray */
  public $columnTypes;

  public static function getModuleInfo() {
    return [
      'title' => 'RockFinder3Master',
      'version' => '1.0.0',
      'summary' => 'Master Instance of RockFinder3 that is attached as PW API Variable',
      'autoload' => 9000,
      'singular' => true,
      'icon' => 'search',
      'requires' => [],
      'installs' => [
        'RockFinder3',
      ],
    ];
  }

  public function init() {
    $this->wire('RockFinder3', $this);
    $this->finders = $this->wire(new WireArray());
    $this->columnTypes = $this->wire(new WireArray());
    $this->getBaseColumns();
    $this->loadColumnTypes(__DIR__."/columnTypes");
  }

  /**
   * Load all column type definitions in given directory
   * @return void
   */
  public function loadColumnTypes($dir) {
    $dir = Paths::normalizeSeparators($dir);
    foreach($this->files->find($dir, ['extensions'=>['php']]) as $file) {
      $file = $this->info($file);
      // try to load the columnType class
      try {
        require_once($file->path);
        $class = "\RockFinder3Column\\{$file->filename}";
        $colType = new $class();
        $colType->type = $file->filename;
        $this->columnTypes->add($colType);
      } catch (\Throwable $th) {
        $this->error($th->getMessage());
      }
    }
  }

  /**
   * Get info for given file
   * @return WireData
   */
  public function info($file) {
    $info = $this->wire(new WireData()); /** @var WireData $info */
    $info->setArray(pathinfo($file));
    $info->path = $file;
    return $info;
  }

  /**
   * Get the columns that are part of the 'pages' db table
   * Those columns need to be treaded differently in queries.
   * @return array
   */
  public function getBaseColumns() {
    $db = $this->config->dbName;
    $result = $this->database->query("SELECT `COLUMN_NAME`
      FROM `INFORMATION_SCHEMA`.`COLUMNS`
      WHERE `TABLE_SCHEMA`='$db'
      AND `TABLE_NAME`='pages';");
    return $this->baseColumns = $result->fetchAll(\PDO::FETCH_COLUMN);
  }

  /**
   * Return a new RockFinder3
   * 
   * This makes it easy to get new finders via the API variable:
   * $finder = $RockFinder3->find("template=foo")->addColumns(...);
   * 
   * @return RockFinder3
   */
  public function find($selector) {
    /** @var RockFinder3 */
    $finder = $this->modules->get('RockFinder3');
    return $finder->find($selector);
  }

  /**
   * Get first row of resultset as stdClass object
   * @return object
   */
  public function getObject($sql) {
    return $this->getObjects($sql)[0];
  }

  /**
   * Execute sql query and return result as array of stdClass objects
   * @return array
   */
  public function getObjects($sql) {
    $result = $this->database->query($sql);
    return $this->addRowIds($result->fetchAll(\PDO::FETCH_OBJ));
  }

  /**
   * Set array keys to id property of objects if one exists
   * This makes it possible to access rows by their id key
   * @return array
   */
  public function addRowIds($_rows) {
    $rows = [];
    foreach($_rows as $row) {
      if(!property_exists($row, "id")) return $_rows;
      $rows[(int)$row->id] = $row;
    }
    return $rows;
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    if($this->input->post->installProcessModule) {
      $this->modules->install('ProcessRockFinder3');
    }

    if(!$this->modules->isInstalled('ProcessRockFinder3')) {
      /** @var InputfieldSubmit $b */
      $b = $this->wire('modules')->get('InputfieldSubmit');
      $b->name = 'installProcessModule';
      $b->value = 'Install the RockFinder3 ProcessModule';
      $b->icon = 'bolt';

      $inputfields->add([
        'type' => 'markup',
        'icon' => 'question-circle-o',
        'label' => 'Do you want to install the ProcessModule?',
        'description' => "The ProcessModule is optional but lightweight. It helps you creating and debugging Finders via the tracy console. To install it, click the button below:",
        'value' => $b->render(),
      ]);
    }
    else {
      $url = $this->pages->get("has_parent=2, name=".ProcessRockFinder3::pageName)->url;
      $link = "<a href='$url'>Click here to open it and write your first finder!</a>";
      $inputfields->add([
        'type' => 'markup',
        'icon' => 'check',
        'label' => 'RockFinder3 Process Module',
        'value' => "The process module is installed - $link",
      ]);
    }
    return $inputfields;
  }

  /**
   * Uninstall actions
   */
  public function ___uninstall() {
    // we do remove both modules manually here
    // the process module can not be listed in getModuleInfo because it is optional
    // if the process module is installed, it causes RockFinder3 to re-install
    // that's why we remove it again here to make sure it is uninstalled
    $this->modules->uninstall('ProcessRockFinder3');
    $this->modules->uninstall('RockFinder3');
  }

  public function __debugInfo() {
    return [
      'finders' => $this->finders,
      'baseColumns' => $this->baseColumns,
    ];
  }
}