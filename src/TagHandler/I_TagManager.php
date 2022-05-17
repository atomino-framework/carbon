<?php namespace Atomino\Carbon\TagHandler;

interface I_TagManager {
	/** @return string[] */
	public function getTags(string|null $search = null): array;
	public function renameTag(string $tag, string|null $to): void;
	public function removeTag(string $tag): void;
}