<?php namespace Atomino\Carbon\TagHandler;

interface I_TagGroupHandler extends I_TagHandler {
	public function group(string $group): I_TagManager;
	/** @return string[] */
	public function groups(): array;
}