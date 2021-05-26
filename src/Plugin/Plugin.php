<?php namespace Atomino\Carbon\Plugin;

use Atomino\Carbon\Generator\CodeWriter;
use Atomino\Carbon\Model;
use Atomino\Neutrons\Attr;

class Plugin extends Attr {

	/**
	 * @param Model $model
	 * @return Plugin[]|Plugin
	 */
	public static function fetch(Model $model): array|static {
		/** @var \Attribute $aAttribute ; */
		$aAttribute = (new \ReflectionClass(static::class))->getAttributes(\Attribute::class)[0]->newInstance();

		if ($aAttribute->flags & \Attribute::IS_REPEATABLE) {
			$plugins = $model->getPlugin(static::class);
			foreach ($plugins as $plugin) $plugin->init($model);
			return $plugins;
		} else {
			$plugin = $model->getPlugin(static::class)[0];
			$plugin->init($model);
			return $plugin;
		}
	}
	protected function init(Model $model) { }
	public function generate(\ReflectionClass $ENTITY, CodeWriter $codeWriter) { }
	public function getTrait(): string|null { return null; }
}