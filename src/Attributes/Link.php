<?php

namespace Atomino\Carbon\Attributes;

use Atomino\Carbon\Entity;
use Atomino\Carbon\Link\LinkHandler;
use Atomino\Carbon\Link\Side;
use Atomino\Neutrons\Attr;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Link extends Attr {

	const LEFT = true;
	const RIGHT = false;

	// Link Entity Name: will be injected by the Model
	protected string $self;
	protected array $sides = [];
	public Side $left;
	public Side $right;

	/**
	 * @param array<string, class-string> $sides
	 * @param int|null $limitLeft
	 * @param int|null $limitRight
	 */
	public function __construct(array $sides, int|null $limitLeft = null, int|null $limitRight = null) {
		$this->left = $left = new Side(Link::LEFT, array_keys($sides)[0], $sides[array_keys($sides)[0]], $limitLeft);
		$this->right = $right = new Side(Link::RIGHT, array_keys($sides)[1], $sides[array_keys($sides)[1]], $limitRight);
		$this->sides = [$left->name => [$left, $right], $right->name => [$right, $left]];
	}

	public function has($name): bool { return array_key_exists($name, $this->sides); }
	public function getHandler(string $name, Entity|null $item = null): LinkHandler { return new LinkHandler($this->sides[$name][0], $this->sides[$name][1], $this->self, $item); }
}

