<?php namespace Atomino\Carbon\Attributes;

use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Entity;
use Atomino\Carbon\Field\JsonField;
use Atomino\Carbon\Finder;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class HasMany extends Relation{
	public function fetch(Entity $item):Finder|null{
		if($item->id === null) return null;
		if($item::model()->getField($this->field) instanceof JsonField){
			return ($this->entity)::search(Filter::where('JSON_CONTAINS(`'.$this->field.'`, $1, "$")', $item->id));
		}else{
			return ($this->entity)::search(Filter::where($this->field.'=$1', $item->id));
		}
	}
}
