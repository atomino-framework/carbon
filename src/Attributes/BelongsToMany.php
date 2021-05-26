<?php namespace Atomino\Carbon\Attributes;

use Atomino\Carbon\Entity;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class BelongsToMany extends Relation{

	public function fetch(Entity $item){
		return ($this->entity)::collect($item->{$this->field});
	}

}
