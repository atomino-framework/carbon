<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;

abstract class TagHandler {

	public function __construct(protected Connection $connection, protected string $table, protected string $source, protected string $field) { }

	/**
	 * @param $search
	 * @return string[]
	 */
	public function get(): array { return $this->search("") }

	/**
	 * @param $search
	 * @return string[]
	 */
	public function search(string $searchl): array {
		$filter = $search === "" ? null : Filter::where(Comparison::field("tag")->instring($search));
		return $this->connection->getFinder()
		                        ->table($this->table)
		                        ->fields("tag")
		                        ->where($filter)
		                        ->asc("tag")
		                        ->values("tag");
	}


	abstract public function build(): void;

	//abstract public function update(int|null $id, string|null|array ...$tags);

	abstract public function rename(string $tag, string|null $to, string|null $batch = null): void;
	abstract public function remove(string $tag, string|null $batch = null): void;

	protected function truncate(): void { $this->connection->query("TRUNCATE TABLE `$this->table`"); }
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