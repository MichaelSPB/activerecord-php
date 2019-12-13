<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

use PDOOCI\PDO;

/**
 * Adapter for OCI (not completed yet).
 * 
 * @package ActiveRecord
 */
class OciAdapter extends Connection
{
	static $QUOTE_CHARACTER = '';
	static $DEFAULT_PORT = 1521;

	public $dsn_params;
	private $info;
	
	protected function __construct($info)
	{
		//$c="oci:dbname=//$info->host/$info->db";
		$c="oci:dbname=$info->host";
		$c = isset($info->charset)?"$c;charset={$info->charset}" : $c;
		$this->info = $info;
//		var_dump ($c);
		try {
			//echo $c;
			$this->connection = new PDO("$c",$info->user,$info->pass,static::$PDO_OPTIONS);
//			$this->connection = new PDO($info,$info->user,$info->pass,static::$PDO_OPTIONS);
			foreach(static::$PDO_OPTIONS as $opt => $val) {
			    $this->connection->setAttribute($opt,$val);
			}
			//$this->connection->query("ALTER SESSION SET NLS_DATE_FORMAT='yyyy-mm-dd hh24:mi:ss'");
			$this->connection->query("ALTER SESSION SET NLS_DATE_FORMAT='DD-MM-YYYY hh24:mi:ss'");
			$this->connection->query("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='DD-MM-YYYY HH24:MI:SS'");
		} catch (PDOException $e) {
			throw new DatabaseException($e);
		}
	}

	public function supports_sequences() { return true; }
	
	public function get_next_sequence_value($sequence_name)
	{
		return $this->query_and_fetch_one('SELECT ' . $this->next_sequence_value($sequence_name) . ' FROM dual');
	}

	public function next_sequence_value($sequence_name)
	{
		return "$sequence_name.nextval";
	}

	public function date_to_string($datetime)
	{
		//$datetime->utc();
		return $datetime->format('d-m-Y H:i:s');
	}

	public function datetime_to_string($datetime)
	{
		//$datetime->utc();
		return $datetime->format('d-m-Y H:i:s');
	}

	// $string = DD-MON-YYYY HH12:MI:SS(\.[0-9]+) AM
	public function string_to_datetime($string)
	{
		$string = str_replace('.000000','',$string) . ' UTC';
		$value = parent::string_to_datetime($string);
		if($value instanceof DateTime) $value->tz();
		return $value;
	}

	public function limit($sql, $offset, $limit)
	{
		$offset = intval($offset);
		$stop = $offset + intval($limit);
		return 
			"SELECT * FROM (SELECT a.*, rownum ar_rnum__ FROM ($sql) a " .
			"WHERE rownum <= $stop) WHERE ar_rnum__ > $offset";
	}

	public function query_column_info($table)
	{
		$sql = 
			"SELECT c.column_name, c.data_type, c.data_length, c.data_scale, c.data_default, c.nullable, " .
				"(SELECT a.constraint_type " .
				"FROM all_constraints a, all_cons_columns b " .
				"WHERE a.constraint_type='P' " .
				"AND a.constraint_name=b.constraint_name " .
				"AND a.table_name = t.table_name AND b.column_name=c.column_name " .
				"AND a.OWNER=b.OWNER " .
				"AND b.OWNER=?) AS pk " .
			"FROM user_tables t " .
			"INNER JOIN user_tab_columns c on(t.table_name=c.table_name) " .
			"WHERE t.table_name=?";
		$user = strtoupper($this->info->user);
		$values = array($user,strtoupper($table));
		$query = $this->query($sql,$values);
//global $app;
//var_dump($app['caches.memcache.host']$query);
		return $query;
	}

	public function query_for_tables()
	{
		return $this->query("SELECT table_name FROM user_tables");
	}

	public function create_column(&$column)
	{
//var_dump($column);
		//$column = array_change_key_case($column, CASE_LOWER);
		$column['column_name'] = strtolower($column['column_name']);
		$column['data_type'] = strtolower(preg_replace('/\(.*?\)/','',$column['data_type']));

		if ($column['data_default'] !== null)
			$column['data_default'] = trim($column['data_default'],"' ");

		if ($column['data_type'] == 'number')
		{
			if ($column['data_scale'] > 0)
				$column['data_type'] = 'decimal';
			elseif ($column['data_scale'] == 0)
				$column['data_type'] = 'int';
		}

		$c = new Column();
		$c->inflected_name	= Inflector::instance()->variablize($column['column_name']);
		$c->name			= $column['column_name'];
		$c->nullable		= $column['nullable'] == 'Y' ? true : false;
		$c->pk				= $column['pk'] == 'P' ? true : false;
		$c->length			= $column['data_length'];
	
		if ($column['data_type'] == 'timestamp')
			$c->raw_type = 'datetime';
		else
			$c->raw_type = $column['data_type'];

		$c->map_raw_type();
		$c->default	= $c->cast($column['data_default'],$this);

		return $c;
	}

	public function set_encoding($charset)
	{
		// is handled in the constructor
	}

	public function native_database_types()
	{
		return array(
			'primary_key' => "NUMBER(38) NOT NULL PRIMARY KEY",
			'string' => array('name' => 'VARCHAR2', 'length' => 255),
			'text' => array('name' => 'CLOB'),
			'integer' => array('name' => 'NUMBER', 'length' => 38),
			'float' => array('name' => 'NUMBER'),
			'datetime' => array('name' => 'DATE'),
			'timestamp' => array('name' => 'DATE'),
			'time' => array('name' => 'DATE'),
			'date' => array('name' => 'DATE'),
			'binary' => array('name' => 'BLOB'),
			'boolean' => array('name' => 'NUMBER', 'length' => 1)
		);
	}
}
?>
