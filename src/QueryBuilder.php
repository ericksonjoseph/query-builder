<?php

require __DIR__ . '/Utils.php';

/**
 * Responsible for building dynamic queries using a common interface
 *
 * ---------- ===== EXAMPLE USAGE ===== ----------
 * 
        $QueryBuilder = new QueryBuilder();

        $params['fields'] = array(
            'TtSampleDatum.upc',
            'TtSampleDatumCopy.am_user_id',
            'TtSample.tagged_date',
            'TtSample.status',
        );

        $params['filters'] = array(
            'upc' => (object)array(
                'value' => '0890',
                'type' => 'contains',
                'meta' => (object)array(
                    'model_name' => 'TtSampleDatum',
                    'model_field' => 'upc',
                ),
            ),
            'tagged_date' => (object)array(
                "value" => "2015-01-01 to 2016-01-27",
                "start" => "2015-01-01 03:00:00",
                "end" => "2016-01-27 03:00:00",
                "type" => "range",
                "meta" =>  (object)array(
                    "model_name" => "TtSample",
                    "model_field" => "tagged_date",
                    "function" => "date",
                )
            ),
        );

        $QueryBuilder->distinct = true;
        $result = $QueryBuilder->build($params);


 * 
 * @author		Erickson Joseph
 */
class QueryBuilder
{

    /**
     * Path to file containg mappings
     * 
     * @var mixed
     * @access public
     */
    public $mapping_file;

    /**
     * timezone
     * 
     * @var mixed
     * @access public
     */
    public $timezone = 'UTC';

    /**
     * Params to create report
     * 
     * @var mixed
     * @access public
     */
    private $params;

    /**
     * mapping
     * 
     * @var mixed
     * @access private
     */
    private $mapping;

    /**
     * Initial table to query on
     * 
     * @var string
     * @access public
     */
    public $default_table = 'tt_sample_data';

    /**
     * Alias to use for the default table
     * 
     * @var string
     * @access public
     */
    public $default_table_alias = 'TtSampleDatum';

    /**
     * Return distinct records
     * 
     * @var boolean
     * @access public
     */
    public $distinct;

    /**
     * select
     * 
     * @var string
     * @access private
     */
    private $select;

    /**
     * join
     * 
     * @var string
     * @access private
     */
    private $join;

    /**
     * where
     * 
     * @var string
     * @access private
     */
    private $where;

    /**
     * Prevents adding duplicate joins
     * 
     * @var mixed
     * @access private
     */
    private $tables_already_joined;

    /**
     * Last error that occured
     * 
     * @var mixed
     * @access private
     */
    private $error;

    /**
     * All errors that occured
     * 
     * @var array
     * @access private
     */
    private $errors = array();

	/**
     * Builds an SQL query based on the fields and filters provided
     * Everything must be properly formatted
	 * 
	 * @param array $params
	 *   User submitted form data.
	 * @return multitype:|boolean
	 */
    public function build($params) 
	{
        date_default_timezone_set($this->timezone);

        if (!$params) {
            $this->error("Parameters needed to build select statement");
            return false;
        }

        if (empty($params['fields'])) {
            $this->error("Report Data Fields missing");
            return false;
        }

        // INIT
        $this->params = $params;
        $this->reset();

        // BUILD SELECT
        $this->buildSelectStatement();

        // WHERE CLAUSE
        $this->where = $this->buildWhereClause();
        $this->select .= " WHERE (1=1) {$this->where} ";

        // FEEDBACK
        return $this->select;
    }

    public function setMappingFile($path)
    {
        $this->mapping_file = $path;
    }

    /**
     * Takes our filters and creates SQL conditions
     *
     * @params Array $filters
     * @access public
     * @return Array Conditions
     */
    public static function buildConditionsFromFilters($filters)
    {
        $conditions = array();

        foreach ($filters as $api_field => $api_data){

            $results = null;
            $condition = '';

            if (isset($api_data->meta)) {

                $field = $api_data->meta->model_name.'.'.$api_data->meta->model_field;

                if (isset($api_data->meta->function) && $api_data->meta->function){
                    $field = $api_data->meta->function . '(' . $field . ')';
                }

                switch ($api_data->type){

                    case 'contain':
                    case 'contains':
                        $condition = $field . ' like "%'.$api_data->value.'%"';
                        $conditions[] = $condition;
                        break;

                    case 'boolean':
                        if ($api_data->value == -1) {
                            // -1 represents "all" (both 0 and 1) so we wont add a condition
                            break;
                        }
                    case 'exact':
                        $condition = $field . ' = "'.$api_data->value.'"';
                        $conditions[] = $condition;
                        break;

                    case 'set':
                        if(!empty($api_data->value)){
                            $key = $field;
                            $conditions[] = $key . ' IN ("' . implode('","', $api_data->value) . '")';
                        }
                        break;

                    case 'range':
                        if (isset($api_data->start)) {
                            $date_from = Utils::formatDateRange($api_data->start, 'Y-m-d H:i:s', 'start');
                            $condition = $field . ' >= "'. $date_from .'"';
                            $conditions []= $condition;
                        }

                        if (isset($api_data->end)) {
                            $date_to = Utils::formatDateRange($api_data->end, 'Y-m-d H:i:s', 'end');
                            $condition = $field . ' <= "'. $date_to .'"';
                            $conditions []= $condition;
                        }

                        break;

                    default:
                        CakeLog::write('debug', "Type not found for query_type " . $api_data->type);
                }
            }

        }

        return $conditions;
    }

    /**
     * Builds select and join strings
     *
     * @param array $fields
     * @access private
     * @return boolean - true on succes
     */
    private function buildSelectStatement()
    {
        $fields = $this->params['fields'];

        // Initialize
        $mapping = $this->getMapping()['fields'];
        $joins = $this->getMapping()['joins'];
        $select = '';

        // Include our default table
        $from = "FROM {$this->default_table} {$this->default_table_alias}";

        // Table no longer needs to be joined
        $this->tables_already_joined[] = $this->default_table_alias;

        // build select statement
        foreach ($fields as $field) {

            // Get the settings for this fields
            if (!isset($mapping[$field])) {
                throw new \Exception("No mapping found for $field.");
            }

            $map = $mapping[$field];

            // Add the join if needed
            if (!isset($map['join'])) {
                throw new \Exception("Missing JOIN settings for $field Please check mapping.");
            }

            // Option to override join settings
            $join = (is_array($map['join']) && !empty($map['join'])) ? (object)$map['join'] : (object)$joins[$map['join']];

            // Add Join
            $this->addJoin($join);

            // Append the select statement
            if (!isset($map['rel_join'])) {
                $select .= "{$map['join']}.{$map['field']} AS {$map['alias']}, ";
            } else {
                $select .= "{$map['rel_join']['alias']}.{$map['rel_join']['display']} AS {$map['alias']}, ";
                $parts = explode('.', $field);
                $this->addJoin(
                    (object)array(
                        'type' => $map['rel_join']['type'],
                        'table' => $map['rel_join']['table'],
                        'alias' => $map['rel_join']['alias'],
                        'on' => $map['rel_join']['on'], 
                        'model' => $parts[0],
                        'field' => $parts[1],
                    )
                );
            }
        }

        // Finally build statement
        $select = rtrim(trim($select), ',');

        $distinct = ($this->distinct) ? 'DISTINCT ' : '';
        $this->select = "SELECT {$distinct}{$select} {$from} {$this->join}";

        return true;
    }

    /**
     * Recursively Adds the join if one hasnt already been added with the same alias
     *
     * @TODO Validate params.. maybe with Join class
     *
     * @param StdObject $join
     * @access public
     * @return void
     */
    private function addJoin($join)
    {

        if (!($join instanceof \StdClass)){
            throw new \Exception('Expected a valid Join object');
        }

        // If the table we are trying to join on has not been joined yet we need to now 
        if (!in_array($join->model, $this->tables_already_joined)){
            $parent_join = $this->getMapping()['joins'][$join->model];
            if (!$parent_join){
                throw new \Exception("No settings for table $join->model This means your join settings are inconsistent");
            }
            $this->addJoin((object)$parent_join);
        }

        // Concat to Join String
        if (!in_array($join->alias, $this->tables_already_joined)){
            $this->join .= "{$join->type} JOIN {$join->table} {$join->alias} ON {$join->model}.{$join->field} = {$join->alias}.{$join->on} ";
            $this->tables_already_joined[] = $join->alias;
        }
    }

    /**
     * Builds a where clause with the given filters
     * Filters must be properly formatted
     *
     * @access private
     * @return void
     */
    private function buildWhereClause()
    {
        $where = null;

        $conditions = $this->buildConditionsFromFilters($this->params['filters']);

        foreach($conditions as $i => $cond) {
            $where .= "AND ({$cond})";
        }

        return $where;
    }

    /**
     * Prepare to build a new query
     *
     * @access private
     * @return void
     */
    private function reset()
    {
        $this->join = '';
        $this->where = '';
        $this->select = '';
        $this->tables_already_joined = array();
    }

    private function error($msg)
    {
        $this->error = $msg;
        $this->errors[] = $msg;
    }

	/**
	 * Returns a list of available report columns
	 * 
	 * @return Array
	 */
	private function getMapping() {

        if (!$this->mapping){
            $this->loadMapping();
        }

        return $this->mapping;
	}

    /**
     * Load mappings from a file
     *
     * @param string $path
     * @access private
     * @return void
     */
    private function loadMapping()
    {
        if (!$this->mapping_file) {
            throw new \Exception("Need a file to load mappings from");
        }

        // @TODO no contents?
        $content = file_get_contents($this->mapping_file);

        $array = json_decode($content, true);
        if (!$array) {
            throw new \Exception("Invalid mapping file");
        }

        $this->mapping = $array;
    }
}
