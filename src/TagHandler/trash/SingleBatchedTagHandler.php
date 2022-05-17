<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use function Atomino\debug;

class SingleBatchedTagHandler extends BatchedTagHandler {

	public function __construct(protected Connection $connection, protected string $table, protected string $source, protected string $field, protected string $batchField) { }

	public function build(): void {
		if (is_null($this->batchField)) {
			$this->truncate();
			$this->connection->query("INSERT IGNORE INTO `$this->table`
				SELECT DISTINCT `$this->field` AS `tag`, NULL AS `batch`
				FROM `$this->source`
				WHERE `$this->field` IS NOT NULL AND `$this->field` != ''
				ORDER BY `$this->field`");
		} elseif (is_null($batch)) {
			$this->truncate();
			$this->connection->query("INSERT IGNORE INTO `$this->table`
					SELECT `$this->field` AS `tag`, `$this->batchField` AS `batch`
					FROM `$this->source`
					WHERE `$this->field` IS NOT NULL AND `$this->field` != ''
					GROUP BY `$this->field`,`$this->batchField` HAVING COUNT(*) > 1
					ORDER BY `$this->field`");
		} else {
			$this->truncate($batch);
			$this->connection->query("INSERT IGNORE INTO `$this->table` 
					SELECT DISTINCT `$this->field` AS `tag`, " . $this->connection->quote($batch) . " AS `batch` 
					FROM `$this->source` 
					WHERE `$this->field` IS NOT NULL AND `$this->field` != ''
						AND `$this->batchField` = " . $this->connection->quote($batch) . "
					GROUP BY `$this->field`,`$this->batchField` HAVING COUNT(*) > 1
					ORDER BY `$this->field`");
		}

	}

	public function delete(int $id){
		$idComparison = Comparison::field("id")->is($id)->getSql($this->connection);
		$tagSelect = "SELECT tag FROM test WHERE " . $idComparison;
		$countSelect = "SELECT count(*) FROM test WHERE tag = ( $tagSelect )";
		$this->connection->query("DELETE FROM __tags WHERE $notNewTagComparison AND tag = ( $tagSelect) AND 1 = ( $countSelect );");
	}
	
	public function insert(string $batch, string ...$tags){
		$tag = count($tags) ? $tags[0] : null;
		if ($tag !== null && $tag !== "") $this->connection->getSmart()->insert($this->table, ["tag" => $tag, "batch"=>$batch], true);
	}
	
	public function update(int|null $id, string|null $batch = null, null|string ...$tags) {
		$tag = count($tags) ? $tags[0] : null;
		if (is_null($this->batchField)) $this->unBatchedUpdate($id, $tag);
		else $this->batchedUpdate($id, $batch, $tag);

		if (is_null($batch) !== is_null($this->batchField)) throw new \Exception("Tag batch incosistency!");
	}

	protected function unBatchedUpdate(int|null $id, null|string $tag) {
		if (!is_null($id)) {
			$idComparison = Comparison::field("id")->is($id)->getSql($this->connection);
			$notNewTagComparison = Comparison::field("tag")->not($tag)->getSql($this->connection);
			$tagSelect = "SELECT tag FROM test WHERE " . $idComparison;
			$countSelect = "SELECT count(*) FROM test WHERE tag = ( $tagSelect )";
			$this->connection->query("DELETE FROM __tags WHERE $notNewTagComparison AND tag = ( $tagSelect) AND 1 = ( $countSelect );");
		}
		if ($tag !== null) $this->connection->getSmart()->insert($this->table, ["tag" => $tag], true);
	}
	protected function batchedUpdate(int|null $id, string|null $batch = null, null|string ...$tags) {
		if (!is_null($id)) {
			$idComparison = Comparison::field("id")->is($id)->getSql($this->connection);
			$tagSelect = "SELECT tag FROM test WHERE " . $idComparison;
			$batchSelect = "SELECT batch FROM test WHERE " . $idComparison;
			$countSelect = "SELECT count(*) FROM test WHERE tag = ( $tagSelect ) AND batch = ( $batchSelect )";
			$this->connection->query("DELETE FROM __tags WHERE  tag = ( $tagSelect) AND batch = ( $batchSelect ) AND 1 = ( $countSelect );");
		}
		if ($tag !== null) $this->connection->getSmart()->insert($this->table, ["tag" => $tag, "batch" => $batch], true);
	}


	public function rename(string $tag, string|null $to, string|null $batch = null): void {
		$filter = Filter::where(Comparison::field($this->field)->is($tag));
		if (!is_null($batch)) $filter->and(Comparison::field($this->batchField)->is($batch));
		$this->connection->getSmart()->update($this->source, $filter, [$this->field => $to]);

		$filter = Filter::where(Comparison::field("tag")->is($tag));
		if (!is_null($batch)) $filter->and(Comparison::field("batch")->is($batch));
		if ($to === null) $this->connection->getSmart()->delete($this->table, $filter);
		else $this->connection->getSmart()->update($this->table, $filter, ["tag" => $to]);
	}

	public function remove(string $tag, string|null $batch = null): void { $this->rename($tag, null, $batch); }
}
