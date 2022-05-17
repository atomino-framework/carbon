<?php //namespace Atomino\Carbon\TagHandler;
//
//use Atomino\Carbon\Database\Connection;
//use Atomino\Carbon\Database\Finder\Comparison;
//use Atomino\Carbon\Database\Finder\Filter;
//use Atomino\Carbon\Entity;
//use Atomino\Carbon\EntityInterface;
//use Atomino\Carbon\Repository;
//
////abstract class TagManager {
////
////	protected bool $isMulti = false;
////	protected bool|string $batchField = false;
////
////	public function __construct(
////		protected Connection $connection,
////		protected string     $table,
////		protected string     $source,
////		protected string     $field
////	) {
////	}
////
////	public function getMultiHandler(): MultiHandler { return new MultiHandler($this->connection, $this->table, $this->source, $this->field); }
////	public function getMultiGroupedHandler(string $batchField): MultiGroupedHandler { return new MultiGroupedHandler($this->connection, $this->table, $this->source, $this->field, $batchField); }
////	public function getSingleHandler(): SingleHandler { return new SingleHandler($this->connection, $this->table, $this->source, $this->field); }
////	public function getSingleGroupedHandler(string $batchField): SingleGroupedHandler { return new SingleGroupedHandler($this->connection, $this->table, $this->source, $this->field, $batchField); }
////
////}
//
///*
//
//Handler Interface
//- build
//
//Manager Interface
//- getTags
//- searchTags
//- rename
//- remove
// */
//
//
//abstract class AbstractTagHandler {
//	protected Connection $connection;
//	protected string $table;
//
//	protected function rebuild($select) {
//		$this->connection->getPdo()->beginTransaction();
//		try {
//			$this->connection->query("TRUNCATE TABLE `$this->table`");
//			$this->connection->query("INSERT INTO `$this->table` ( $missingSelect )");
//		} catch (\Throwable $exception) {
//			$this->connection->getPdo()->rollBack();
//			throw $exception;
//		}
//		$this->connection->getPdo()->commit();
//	}
//}
//
//class TagHandler extends AbstractTagHandler implements I_TagHandler, I_TagManager {
//	public function __construct(
//		protected Connection $connection,
//		protected string     $table,
//		protected string     $source,
//		protected string     $sourceTagField
//	) {
//	}
//
//	public function rebuild(): void {
//		parent::rebuild("SELECT DISTINCT `$this->sourceTagField` as `tag` FROM `$this->sourceTag`");
//	}
//
//	protected function getTags(?string $search = null): array {
//		$filter = (!is_null($search) && trim($search) !== "")
//			? Filter::where(Comparison::field("tag")->instring($search))
//			: null;
//		return $this->connection->getFinder()->fields("tag")->table($this->table)->where($filter)->asc("tag")->values();
//	}
//
//	public function renameTag(string $tag, string|null $to): void {
//		$to = (is_null($to) || trim($to) === "") ? null : trim($to);
//		if ($tag === $to) return;
//
//		try {
//			$this->connection->getSmart()->update($this->source, [$this->sourceTagField => $to]);
//		} finally {
//			$this->rebuild();
//		}
//	}
//	public function removeTag(stirng $tag): void { $this->renameTag($tag, null); }
//}
//
//class TagSetHandler extends TagHandler {
//	public function rebuild(): void {
//		parent::rebuild("SELECT DISTINCT `data`.`tag` as `tag` FROM `$this->source`, JSON_TABLE(`$this->source`.`$this->sourceTagField`,'$[*]' COLUMNS(`tag` Varchar(255) PATH '$')) `data`");
//	}
//	public function renameTag(string $tag, string|null $to): void {
//		$to = (is_null($to) || trim($to) === "") ? null : trim($to);
//		if ($tag === $to) return;
//
//		try {
//			$records = $this->connection->getFinder()->table($this->source)->fields("id", $this->sourceTagField)->where(Filter::where(Comparison::field($this->sourceTagField)->json_contains($tag)))->records();
//			foreach ($records as $record) {
//				$tags = $record[$this->sourceTagField];
//				$id = $record["id"];
//				$tags = is_null($to)
//					? array_splice($tags, array_search($tag, $tags), 1)
//					: $tags = array_unique(array_replace(json_decode($tags), [$tag => $to]));
//				$this->connection->getSmart()->update($this->source, [$tag => json_encode($tags)], Comparison::field("id")->is($id));
//			}
//		} finally {
//			$this->rebuild();
//		}
//	}
//	public function removeTag(stirng $tag): void { $this->renameTag($tag, null); }
//}
//
//
//
//class TagGroupHandler extends AbstractTagHandler implements I_TagGroupHandler {
//	public function __construct(
//		protected Connection $connection,
//		protected string     $table,
//		protected string     $source,
//		protected string     $sourceTagField,
//		protected string     $sourceGroupField
//	) {
//	}
//
//	public function rebuild(): void { parent::rebuild("SELECT DISTINCT `data`.`tag` as `tag`, `$this->sourceGroupField` as `group` FROM `$this->source`, JSON_TABLE(`$this->source`.`$this->sourceTagField`,'$[*]' COLUMNS(`tag` Varchar(255) PATH '$')) `data`"); }
//
//	/** @return string[] */
//	public function groups(): array { return $this->connection->getFinder()->select("DISTINCT `group`")->table($this->table)->asc("group")->values(); }
//	public function group(string $group): I_TagManager { return new TagGroupManager($this->connection, $this->table, $this->source, $this->sourceTagField, $this->sourceGroupField, $group); }
//}
//
//class TagGroupManager implements I_TagManager {
//	public function __construct(
//		protected Connection $connection,
//		protected string     $table,
//		protected string     $source,
//		protected string     $sourceTagField,
//		protected string     $sourceGroupField,
//		protected string     $group
//	) {
//	}
//	public function getTags(?string $search = null): array {
//		$filter = Filter::where(Comparison::field("group")->is($this->group));
//		if (is_null($search) || trim($search) === "") $filter->and(Comparison::field("tag")->instring($search));
//		return $this->connection->getFinder()->fields("tag")->table($this->table)->where($filter)->asc("tag")->values();
//	}
//	public function removeTag($tag): void {
//		// TODO: Implement removeTag() method.
//	}
//	public function renameTag($tag, $to): void {
//		// TODO: Implement renameTag() method.
//	}
//}
//
//
//class TagSetGroupHandler extends TagGroupHandler {
//
//}
//
//
////interface I_TagManagerEntityEventHandlers {
////	public function addTagsOf($id):void;
////	public function removeTagsOf(int $id):void;
////}
//
//
///** @var I_GlobalTagHandler $s */
//$s->build();
//$s->getTags("asdf");
//$s->removeTag("asdf");
//$s->renameTag("asdf", "jklé");
///** @var TagSetGroupHandler $sb */
//$sb->build();
//$sb->group("batch-name")->getTag("asdf");
//$sb->group("batch-name")->removeTag("asdf");
//$sb->group("batch-name")->renameTag("asdf", "jklé");
//
//
//
///*
//
//# SINGLE
//# missing tags
//SELECT DISTINCT tag FROM test WHERE tag NOT IN (SELECT DISTINCT tag FROM __tags);
//# unwanted tags
//SELECT DISTINCT tag FROM __tags WHERE tag NOT IN (SELECT DISTINCT tag FROM test);
//
//# SINGLE-Grouped
//
//# missing Grouped tags
//SELECT DISTINCT tag, batch FROM test WHERE (tag, batch) NOT IN (SELECT tag, batch FROM __tags) ORDER BY batch, tag;
//
//# unwanted tags
//SELECT DISTINCT tag, batch FROM __tags WHERE (tag, batch) NOT IN (SELECT DISTINCT tag, batch FROM test) ORDER BY batch, tag;
//
//# MULTI (tag: acceptable, batch: allocation)
//
//# missing tags
//SELECT DISTINCT data.tag FROM test, JSON_TABLE(test.tags,"$[*]" COLUMNS(tag Varchar(255) PATH "$")) data WHERE data.tag not in (SELECT DISTINCT tag FROM __tags);
//
//# unwanted tags
//SELECT DISTINCT tag FROM __tags WHERE tag NOT IN  (SELECT DISTINCT data.tag FROM test, JSON_TABLE(test.tags,"$[*]" COLUMNS(tag Varchar(255) PATH "$")) data);
//
//# MULTI-Grouped
//
//# missing tags
//SELECT DISTINCT data.tag, batch FROM test, JSON_TABLE(test.tags,"$[*]" COLUMNS(tag Varchar(255) PATH "$")) data WHERE (data.tag, batch) not in (SELECT DISTINCT tag, batch FROM __tags);
//
//# unwanted tags
//SELECT DISTINCT tag, batch FROM __tags WHERE (tag, batch) NOT IN  (SELECT DISTINCT data.tag, batch FROM test, JSON_TABLE(test.tags,"$[*]" COLUMNS(tag Varchar(255) PATH "$")) data);
//
//
//*/