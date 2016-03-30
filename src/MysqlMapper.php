<?php

require_once __DIR__ . '/Mapper.php';

class MysqlMapper extends Mapper {

    public $error;

    private $destination;

    private $pdo;

    /**
     * Dynamically Generated mapping
     * 
     * @var mixed
     * @access private
     */
    private $mapping;

    /**
     * Cutom mapping Any mappings here will override $mapping
     * 
     * @var mixed
     * @access private
     */
    private $custom_mapping = array(
        'TtSampleDatumCopy.am_user_id' => array(
            'label'   => 'Copy Performed by',
            'type'    => 'varchar',
            'join' => 'TtSampleDatumCopy',
            'field' => 'am_user_id',
            'alias' => 'TtSampleDatumCopy_am_user_id',
            'rel_join' => array(
                'type' => 'LEFT',
                'table' => 'am_users',
                'alias' => 'AmUserCopyPerformedBy',
                'on' => 'id',
                'display' => 'username',
            ),
        ),
    );

    /**
     * Holds the configs that tell us how to join tables
     //@TODO Dont join default table on itself
     * @TODO Find easy way to map and maintain
     * 
     'TtSampleDatumCopy' => array( // The table that this field belongs to must be joined to the query
         'type' => 'LEFT',
         'table' => 'tt_sample_datum_copies', // table this field belongs to
         'alias' => 'TtSampleDatumCopy',
         'on' => 'datum_base_item_id', // field in this field's table to join on
         'model' => 'TtSampleDatum', // The table to join on (will be joined if not already)
         'field' => 'base_item_id', // The field to join on
     ),
     * @var mixed
     * @access private
     */
    private $join_settings;

    public function __construct ($host, $dbname, $user, $pass)
    {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function loadCustomMapping($path)
    {
        $this->custom_mapping = $this->loadJsonFile($path);
    }

    public function loadJoinSettings($path)
    {
        $this->join_settings = $this->loadJsonFile($path);
    }

    private function loadJsonFile($path)
    {
        $contents = $this->loadFile($path);

        // @TODO invalid json?
        return json_decode($contents, true);
    }

    private function loadFile($path)
    {
        if (!file_exists($path)){
            throw new \Exception("File $path does not exist");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->error = "Failed to get contents of file $path";
            return false;
        }

        return $contents;
    }

    public function map($create_file = true)
    {
        $this->setupMapping();

        $mapping = json_encode($this->mapping);

        if (!$create_file) {
            return $mapping;
        }

        return $this->createFile('mapping.json', $mapping);
    }

    private function createFile($filename, $content)
    {
        // @TODO what is destination = "0"?
        if (!$this->destination) {
            throw new \Exception("Missing destination");
        }

        $output = $this->destination . "/" . $filename;
        $written = file_put_contents($output, $content);

        if ($written === false) {
            $this->error = "Failed to write file $output";
            return false;
        }

        chmod($output, 0664);
        return true;
    }

    public function setDestination($path)
    {
        if (strstr($path, '../')) {
            throw new \Exception("Invalid destination path");
        }

        $this->destination = $path;
    }

    /**
     * getJoinSettings
     *
     * @access private
     * @return void
     */
    private function getJoinSettings()
    {
        if (!$this->join_settings) {
            throw new \Exception("Please provide join_settings");
        }

        return $this->join_settings;
    }

    /**
     * Custom mappings will override dynamic mapping
     * @TODO Decide how we want to set these
     *
     * @param mixed $unique_field
     * @access private
     * @return void
     */
    private function getCustomMapping()
    {
        if (!$this->custom_mapping) {
            throw new \Exception("Please provide custom_mappings");
        }

        return $this->custom_mapping;
    }

    /**
     * Querys for all fields in tables defined in join_settings
     * and sets up basic mappings for all of them then merges with
     * our custom mappings
     * @TODO if we decide to keep this idea, get all tables in one call
     *
     * @access private
     * @return void
     */
    private function setupMapping()
    {
        $this->mapping = array();
        $fields = array();

        $aliases = array_keys($this->getJoinSettings());

        foreach ($aliases as $i => $alias) {
            $fields = array_merge($fields, $this->mapAlias($alias));
        }

        $this->mapping['fields'] = array_replace_recursive($fields, $this->getCustomMapping());
        $this->mapping['joins'] = $this->getJoinSettings();
    }

    /**
     * @TODO Give users a way to inject their models or connect to db to map fields dynamically
     * Adds mappings for the given table alias by hitting the database for fields
     * then adds relavent data to our mapping
     *
     * @param String $alias
     * @access private
     * @return void
     */
    private function mapAlias($alias)
    {
        $field_settings = array();
        $table_settings = $this->getJoinSettings();

        // Make sure settings exist
        if (!isset($table_settings[$alias])) {
            $this->error("No Join Settings for $alias");
            return false;
        }

        $table_name = $table_settings[$alias]['table'];

        // Query
        $q = "SHOW COLUMNS FROM {$table_name}";
        $statement = $this->pdo->query($q);

        $data = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as $i => $arr) {
            $entry = array(
                'label' => $arr['Field'],
                'type' => $arr['Type'],
                'join' => $alias,
                'field' => $arr['Field'],
                'alias' => $alias.'_'.$arr['Field'],
            );

            $field_settings[$alias . '_' . $arr['Field']] = $entry;
        }
        return $field_settings;
    }
}
