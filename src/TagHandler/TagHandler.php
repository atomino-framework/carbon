<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;

abstract class TagHandler{
	public function __construct(protected Connection $connection, protected string $table, protected string $source, protected string $field) {}

	/**
	 * @param $search
	 * @return string[]
	 */
	public function get():array { return $this->connection->getSmart()->getValues("SELECT tag FROM `$this->table` ORDER BY tag"); }

	/**
	 * @param $search
	 * @return string[]
	 */
	public function search($search):array{
		$search = "%".$search."%";
		return $this->connection->getSmart()->getValues("SELECT * FROM `$this->table` WHERE tag like $1 ORDER BY tag", $search);
	}

	abstract public function update(bool $truncate = false):void;
	abstract public function remove(string $tag):void;
	abstract public function rename(string $tag, string $to):void;

	public function truncate():void {
		if ($this->connection->getSmart()->tableExists(($this->table))) $this->connection->query("TRUNCATE TABLE `$this->table`");
	}

}



/*

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `getTags`(
arg_table_name VARCHAR(30),
arg_field_name VARCHAR(30),
arg_output_table_name VARCHAR(30)
)
BEGIN
	DECLARE i INT default 0;
	DECLARE path VARCHAR(255);
	SET @tag_count = 0;

	SET @query = CONCAT('CREATE TABLE IF NOT EXISTS `', arg_output_table_name, '` ( tag VARCHAR(255), UNIQUE KEY `tag` (`tag`) )');
	PREPARE q FROM @query; EXECUTE q; DEALLOCATE PREPARE q;

	SET @query = CONCAT('SELECT MAX(JSON_LENGTH(`', arg_field_name, '`)) INTO @tag_count FROM `',arg_table_name,'`');
	PREPARE q FROM @query; EXECUTE q; DEALLOCATE PREPARE q;

	forloop: LOOP
		SET path = CONCAT("$[",i,"]");
		SET @query = CONCAT('
		INSERT IGNORE `', arg_output_table_name, '`
		SELECT trim(json_unquote(JSON_EXTRACT(`', arg_field_name, '`, "',path,'")))
		FROM `', arg_table_name, '`
		WHERE trim(json_unquote(JSON_EXTRACT(`', arg_field_name, '`, "',path,'"))) != ""
		');
		PREPARE q FROM @query; EXECUTE q; DEALLOCATE PREPARE q;

    	SET i = i + 1;
    	IF i < @tag_count THEN
      		ITERATE forloop;
    	END IF;
    	LEAVE forloop;
  	END LOOP forloop;
END;;
DELIMITER ;

*/