<?php namespace Atomino\Carbon\Database\Descriptor\Field;

class Field{

	protected string $name;
	protected ?string $default;
	protected bool $nullable;
	protected string $type;
	protected string $key;
	protected string $extra;
	protected string $comment;
	protected bool $virtual;
	protected string $typeString;

	protected bool $autoIncrement = false;
	protected bool $autoInsert = false;
	protected bool $autoUpdate = false;

	static function create($descriptor): static|null{
		return match (strtoupper($descriptor["DATA_TYPE"])) {
			'INTEGER', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'BIGINT' => new IntegerField($descriptor),
			'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT' => new StringField($descriptor),
			'LONGTEXT' => $descriptor["COLUMN_COMMENT"] === 'json'
				? new JsonField($descriptor)
				: new StringField($descriptor),
			'DOUBLE', 'FLOAT' => new FloatField($descriptor),
			'ENUM' => new EnumField($descriptor),
			'SET' => new SetField($descriptor),
			'JSON' => new JsonField($descriptor),
			'DATE' => new DateField($descriptor),
			'DATETIME' => new DateTimeField($descriptor),
			'TIMESTAMP' => new TimestampField($descriptor),
			'TIME' => new TimeField($descriptor),
			default => new static($descriptor)
		};
	}

	protected function __construct(array $descriptor){
		$this->name = $descriptor["COLUMN_NAME"];
		$this->default = $descriptor["COLUMN_DEFAULT"];
		$this->nullable = $descriptor["IS_NULLABLE"] === 'YES';
		$this->type = $descriptor["DATA_TYPE"];
		$this->key = $descriptor["COLUMN_KEY"];
		$this->extra = $descriptor["EXTRA"];
		$this->comment = $descriptor["COLUMN_COMMENT"];
		$this->virtual = str_contains($descriptor['EXTRA'], 'VIRTUAL');
		$this->typeString = $descriptor["COLUMN_TYPE"];
	}

	public function getName(): string{ return $this->name; }
	public function getDefault(): mixed{ return $this->default; }
	public function isNullable(): bool{ return $this->nullable; }
	public function isVirtual(): bool{ return $this->virtual; }
	public function isPrimary(): bool{ return $this->key === 'PRI'; }
	public function getComment(): mixed{ return $this->comment; }
	public function getType(): string{ return $this->type; }
	public function getKey(): string{ return $this->key; }
	public function getExtra(): string{ return $this->extra; }
	public function getTypeString(): string{ return $this->typeString; }

	public function isAutoIncrement(): bool{ return $this->autoIncrement; }
	public function isAutoInsert(): bool{ return $this->autoInsert || $this->autoIncrement || $this->virtual; }
	public function isAutoUpdate(): bool{ return $this->autoUpdate || $this->autoIncrement || $this->virtual; }

}