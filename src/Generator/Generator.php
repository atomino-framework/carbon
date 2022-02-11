<?php namespace Atomino\Carbon\Generator;

use Application\Entity\Content;
use Application\Entity\Tag;
use Atomino\Carbon\Attributes\BelongsTo;
use Atomino\Carbon\Attributes\BelongsToMany;
use Atomino\Carbon\Attributes\HasMany;
use Atomino\Carbon\Attributes\Protect;
use Atomino\Carbon\Attributes\RequiredField;
use Atomino\Carbon\Attributes\Virtual;
use Atomino\Carbon\Database\Descriptor;
use Atomino\Carbon\Field\Attributes\FieldDescriptor;
use Atomino\Carbon\Model;
use Atomino\Carbon\Plugin\Plugin;
use Atomino\Core\Cli\Style;
use Atomino\Core\PathResolverInterface;
use Atomino\Neutrons\CodeFinder;
use CaseHelper\CamelCaseHelper;
use CaseHelper\SnakeCaseHelper;
use Riimu\Kit\PHPEncoder\PHPEncoder;

class Generator {

	const ATOM_SHADOW_ENTITY_NS = 'Entity';
	const ATOM_ENTITY_FINDER_NS = 'EntityFinder';
	const ATOM_ENTITY_HELPERS_NS = 'EntityHelper';

	private PHPEncoder $encoder;
	private string $entityPath;
	private string $shadowPath;
	private string $helperPath;

	public function __construct(private string $namespace, private string $atomsNamespace, private Style $style, private CodeFinder $codeFinder, private PathResolverInterface $pathResolver) {
		$this->encoder = new PHPEncoder();
		$this->entityPath = substr(realpath($this->codeFinder->Psr4ResolveNamespace($this->namespace)), strlen($this->pathResolver->path()));
		$this->shadowPath = substr(realpath($this->codeFinder->Psr4ResolveNamespace(trim($this->atomsNamespace, '\\') . '\\' . static::ATOM_SHADOW_ENTITY_NS)), strlen($this->pathResolver->path()));
		$this->helperPath = $this->shadowPath;
	}

	private function getTranslate(string $name, string $table, CodeWriter|null $cw = null): array {

		return [
			"{{name}}"             => $name,
			"{{table}}"            => $table,
			"{{entity-namespace}}" => trim($this->namespace, '\\'),
			"{{shadow-namespace}}" => trim($this->atomsNamespace, '\\') . '\\' . static::ATOM_SHADOW_ENTITY_NS,
			"{{helper-namespace}}" => trim($this->atomsNamespace, '\\') . '\\' . static::ATOM_ENTITY_HELPERS_NS,
			"#:code"               => !is_null($cw) ? $cw->getCode() : "",
			"#:annotation"         => !is_null($cw) ? $cw->getAnnotation() : "",
			"#:attribute"          => !is_null($cw) ? $cw->getAttribute() : "",
			"{{interface}}"        => !is_null($cw) ? $cw->getInterface() : "",
		];
	}

	public function create(string $name) {
		$table = (new CamelCaseHelper())->toSnakeCase($name);
		$class = ucfirst((new SnakeCaseHelper())->toCamelCase($table));

		$translate = $this->getTranslate($class, $table);

		$files = [
			"entity.txt" => "{$this->entityPath}/{$class}.php",
			"shadow.txt" => "{$this->shadowPath}/_{$class}.php",
			//			"finder.txt" => "{$this->finderPath}/_{$class}.php",
		];

		$this->style->_section('Create base entity "' . $class . '"');

		foreach ($files as $templateFile => $file) {

			$this->style->_task($file);
			$file = $this->pathResolver->path($file);

			if (file_exists($file)) {
				$this->style->_task_warn('already exists');
			} else {
				$template = file_get_contents(__DIR__ . '/$resources/' . $templateFile);
				$template = strtr($template, $translate);
				file_put_contents($file, $template);
				$this->style->_task_ok();
			}
		}
	}

	public function generate() {

		$summary = [];

		$style = $this->style;
		$modified = false;
		$entities = $this->codeFinder->Psr4ClassSeeker($this->namespace);
		$helpers = [];

		/** @var \Atomino\Carbon\Entity $entity */
		foreach ($entities as $entity) {
			$errors = [];

			$ENTITY = new \ReflectionClass($entity);
			$class = $ENTITY->getShortName();
			$model = new Model($entity);
			$cw = new CodeWriter();

			$table = $model->getConnection()->getDescriptor()->getTable($model->getTable());

			#region table check
			if (is_null($table)) {
				$errors[] = [$class, $model->getTable() . ' table does not exists!'];
			} elseif ($table->isView() && $model->isMutable()) {
				$errors[] = [$class, $model->getTable() . ' Storage is a VIEW. Entity should be immutable!'];
			}

			#endregion

			if (count($errors) === 0) {
				$fields = $this->fetchFields($model, $table, $errors);
				$eHelpers = [];

				#region plugins
				foreach ($ENTITY->getAttributes(Plugin::class, \ReflectionAttribute::IS_INSTANCEOF) as $Plugin) {
					$instance = $Plugin->newInstance();
					if (!is_null($trait = $instance->getTrait())) {
						$cw->addCode('use \\' . trim($trait, '\\') . ';');
					}
					$instance->generate($ENTITY, $cw);
				}
				#endregion

				#region fields
				foreach ($fields as $field) {
					/** @var \Atomino\Carbon\Field\Attributes\FieldDescriptor $f_descriptor */
					$f_descriptor = $field['descriptor'];
					/** @var \Atomino\Carbon\Field\Field $f_entity */
					$f_entity = $field['entity'];
					/** @var \Atomino\Carbon\Database\Descriptor\Field\Field $f_db */
					$f_db = $field['db'];
					$f_entityFieldType = $field['entityFieldType'];
					$name = $f_entity->getName();
					$fieldType = $f_descriptor->type . (is_null($f_descriptor->default) ? '|null' : '');

					# region validator-attributes
					$validators = (!$f_db->isVirtual() && !$f_db->isPrimary()) ? $f_entityFieldType::getValidators($f_db) : [];
					foreach ($validators as $validator) {
						$cw->addAttribute(
							'#[Validator("' . $name . '", \\' . $validator[0] . '::class' . (count($validator) > 1 ? ', ' . $this->encoder->encode($validator[1], ['whitespace' => false]) : '') . ')]'
						);
					}
					# endregion

					# region field-attributes
					$cw->addAttribute(
						'#[Field("' . $name . '", \\' . $f_entityFieldType . '::class' . ($f_descriptor->hasOptions ? ', ' . $this->encoder->encode($f_db->getOptions(), ['whitespace' => false]) : '') . ')]'
					);
					# endregion

					#region protect-attributes
					if ($f_db->isPrimary() || $f_db->isVirtual()) {
						$cw->addAttribute(
							'#[Protect("' . $name . '", true, false)]'
						);
					}
					#endregion

					#region immutable-attributes
					if ($f_db->isPrimary() || $f_db->isVirtual()) {
						$cw->addAttribute(
							'#[Immutable("' . $name . '",' . ($f_db->isAutoInsert() ? 'false' : 'true') . ')]'
						);
					}
					#endregion

					#region fields
					$cw->addCode("const " . $name . " = '" . $name . "';");
					#endregion

					#region comparators
					$cw->addAnnotation("@method static \Atomino\Carbon\Database\Finder\Comparison " . $name . "(\$isin = null)");
					#endregion

					#region fields
					$str = $f_entity->isProtected() ? 'protected' : 'public';
					$str .= ' ';
					$str .= $fieldType;
					$str .= ' ';
					$str .= '$' . $name . ' = ' . $this->encoder->encode($f_descriptor->default);
					$str .= ';';
					$cw->addCode($str);
					#endregion

					#region getters
					$getter = $f_entity->getGetter();
					if (!is_null($getter)) {
						$cw->addCode("protected function " . $getter . "():" . $fieldType . "{ return \$this->" . $name . ";}");
					}
					#endregion

					#region setters
					$setter = $f_entity->getSetter();
					if (!is_null($setter)) {
						$cw->addCode("protected function " . $setter . "(" . $fieldType . " \$value){ \$this->" . $name . " = \$value;}");
					}
					#endregion

					#region properties
					if ($f_entity->getGetter() && $f_entity->getSetter()) {
						$cw->addAnnotation("@property " . $fieldType . " \$" . $name);
					} elseif ($f_entity->getGetter()) {
						$cw->addAnnotation("@property-read " . $fieldType . " \$" . $name);
					} elseif ($f_entity->getSetter()) {
						$cw->addAnnotation("@property-write " . $fieldType . " \$" . $name);
					}
					#endregion

					#region enums
					if ($f_descriptor->hasOptions) {
						foreach ($f_db->getOptions() as $option) {
							$cw->addCode("const " . $name . "__" . $option . " = '" . $option . "';");
						}
					}
					#endregion
				}
				#endregion

				#region check-required-fields
				$Requireds = RequiredField::all($ENTITY, $ENTITY->getParentClass(), ...$ENTITY->getTraits(), ...$ENTITY->getParentClass()->getTraits());
				foreach ($Requireds as $Required) {
					//$style->_task('Required field: ' . $Required->field);
					if (!array_key_exists($Required->field, $fields)) {
						//$style->_task_error('missing');
						$errors[] = [$class, 'Required field missing: ' . $Required->field];
					} elseif ($fields[$Required->field]['entityFieldType'] !== $Required->type) {
						//$style->_task_error('type mismatch (' . $Required->type . ')');
						$errors[] = [$class, 'Required type mismatch (' . $Required->type . '): ' . $Required->field];
					} else {
						//$style->_task_ok();
					}
				}
				#endregion

				#region relations
				foreach ($model->getRelations() as $relation) {
					if ($relation instanceof BelongsTo) {
						$cw->addAnnotation("@property-read \\" . $relation->entity . " $" . $relation->target);
					}
					if ($relation instanceof HasMany) {
						$cw->addAnnotation("@property-read \\Application\\Atoms\\EntityFinder\\_" . (new \ReflectionClass($relation->entity))->getShortName() . " $" . $relation->target);
					}
					if ($relation instanceof BelongsToMany) {
						$cw->addAnnotation("@property-read \\" . $relation->entity . "[] $" . $relation->target);
					}
				}
				#endregion

				#region virtuals
				foreach (Virtual::all($ENTITY) as $virtual) {
					if ($virtual->get && $virtual->set) {
						$cw->addAnnotation("@property " . $virtual->type . " \$" . $virtual->field);
					} elseif ($virtual->get) {
						$cw->addAnnotation("@property-read " . $virtual->type . " \$" . $virtual->field);
					} elseif ($virtual->set) {
						$cw->addAnnotation("@property-write " . $virtual->type . " \$" . $virtual->field);
					}
					if ($virtual->get) $cw->addCode("abstract protected function get" . ucfirst($virtual->field) . "():" . $virtual->type . ";");
					if ($virtual->set) $cw->addCode("abstract protected function set" . ucfirst($virtual->field) . "(" . $virtual->type . " \$value):void" . ";");
				}
				#endregion

				#region finder
				$finderName = $this->getFinderName($class);
				$cw->addAnnotation("@method static \\" . trim($this->atomsNamespace, '\\') . '\\' . static::ATOM_ENTITY_HELPERS_NS . "\\" . $finderName . " search( Filter \$filter = null )");
				$eHelpers[] = [
					"template"  => "helper-finder.txt",
					"translate" => array_merge($this->getTranslate($class, $model->getTable()), ["{{finder-name}}" => $finderName]),
				];
				#endregion

				#region links
				if ($model->isLink()) {
					$link = $model->getLink();
					$cw->addAnnotation("@method static \\" . trim($this->atomsNamespace, '\\') . '\\' . static::ATOM_ENTITY_HELPERS_NS . "\\_" . $class . "_LINK_LEFT " . $link->left->name . "(\\" . $link->left->class . "|null \$" . $link->left->name . " = null)");
					$cw->addAnnotation("@method static \\" . trim($this->atomsNamespace, '\\') . '\\' . static::ATOM_ENTITY_HELPERS_NS . "\\_" . $class . "_LINK_RIGHT " . $link->right->name . "(\\" . $link->right->class . "|null \$" . $link->right->name . " = null)");
					$cw->addAttribute("#[\Atomino\Carbon\Attributes\BelongsTo(\"" . $link->left->name . "\", \\" . $link->left->class . "::class, \"left\")]");
					$cw->addAttribute("#[\Atomino\Carbon\Attributes\BelongsTo(\"" . $link->right->name . "\", \\" . $link->right->class . "::class, \"right\")]");
					$cw->addAttribute("#[Protect(\"left\")]");
					$cw->addAttribute("#[Protect(\"right\")]");

					$eHelpers[] = [
						"template"  => "helper-link.txt",
						"translate" => [
							"{{link-class}}"   => $ENTITY->getName(),
							"{{right-class}}"  => $link->right->class,
							"{{right-name}}"   => $link->right->name,
							"{{right-finder}}" => $this->getFinderName($link->right->name),
							"{{right-link}}"   => "_" . $class . "_LINK_RIGHT",
							"{{left-class}}"   => $link->left->class,
							"{{left-name}}"    => $link->left->name,
							"{{left-finder}}"  => $this->getFinderName($link->left->name),
							"{{left-link}}"    => "_" . $class . "_LINK_LEFT",

						],
					];
				}
				#endregion
			}

			#region write shadow entity
			if (count($errors)) {
				foreach ($errors as $error) {
					$style->_task($error[0]);
					$style->_task_error($error[1]);
				}
				$summary[$entity] = false;
			} else {
				$helpers = array_merge($helpers, $eHelpers);
				$translate = $this->getTranslate($class, $model->getTable(), $cw);

				$template = file_get_contents(__DIR__ . '/$resources/shadow.txt');
				$template = strtr($template, $translate);
				$outfile = $this->pathResolver->path("{$this->shadowPath}/_{$class}.php");
				if (file_get_contents($outfile) !== $template) {
					$modified = true;
					$style->_task($class);
					file_put_contents($outfile, $template);
					$style->_task_ok('modified');
				}
				$summary[$entity] = true;
			}
			#endregion
		}


		#region write helper
		if (count($helpers)) {
			$template = file_get_contents(__DIR__ . '/$resources/helper.txt');
			$template = strtr($template, $translate);
			$outfile = $this->pathResolver->path("{$this->helperPath}/@helpers.php");
			file_put_contents($outfile, $template);

			foreach ($helpers as $helper) {
				$template = file_get_contents(__DIR__ . '/$resources/' . $helper["template"]);
				$template = trim(strtr($template, $helper["translate"])) . "\n\n";
				file_put_contents($outfile, $template, FILE_APPEND);
			}
		}
		#endregion

		if ($modified) {
			return 1;
		} else {
			foreach ($summary as $entity => $success) {
				$style->_task($entity);
				if ($success) $style->_task_ok();
				else $style->_task_error();
			}
			$style->writeln("<fg=green;options=bold>done</>");
			return 0;
		}
	}

	private function getFinderName(string $class) { return "_" . $class . "_FINDER"; }

	private function fetchFields(Model $model, Descriptor\Table $table, &$errors): array {
		$style = $this->style;

		$fields = [];

		foreach ($table->getFields() as $name => $dbField) {
			// $style->_task($name);

			# region getType
			/** @var \Atomino\Carbon\Field\Field $entityFieldType */
			$entityFieldType = match (get_class($dbField)) {
				\Atomino\Carbon\Database\Descriptor\Field\DateField::class => \Atomino\Carbon\Field\DateField::class,
				\Atomino\Carbon\Database\Descriptor\Field\DateTimeField::class,
				\Atomino\Carbon\Database\Descriptor\Field\TimestampField::class => \Atomino\Carbon\Field\DateTimeField::class,
				\Atomino\Carbon\Database\Descriptor\Field\EnumField::class => \Atomino\Carbon\Field\EnumField::class,
				\Atomino\Carbon\Database\Descriptor\Field\FloatField::class => \Atomino\Carbon\Field\FloatField::class,
				\Atomino\Carbon\Database\Descriptor\Field\IntegerField::class => \Atomino\Carbon\Field\IntField::class,
				\Atomino\Carbon\Database\Descriptor\Field\JsonField::class => \Atomino\Carbon\Field\JsonField::class,
				\Atomino\Carbon\Database\Descriptor\Field\SetField::class => \Atomino\Carbon\Field\SetField::class,
				\Atomino\Carbon\Database\Descriptor\Field\StringField::class => \Atomino\Carbon\Field\StringField::class,
				\Atomino\Carbon\Database\Descriptor\Field\TimeField::class => \Atomino\Carbon\Field\TimeField::class,
				default => null
			};
			if ($dbField->getTypeString() === 'tinyint(1)') $entityFieldType = \Atomino\Carbon\Field\BoolField::class;
			# endregion

			if (is_null($entityFieldType)) {
				//$style->_task_error('unsupported type: ' . $dbField->getTypeString());
				$errors[] = [$table->getName(), 'Unsupported type: ' . $dbField->getTypeString()];
			} else {
				$_fieldDescriptor = FieldDescriptor::get(new \ReflectionClass($entityFieldType));

				//$style->_task_ok($dbField->getTypeString());
				if ($model->hasField($name)) {
					if ($model->getField($name)->isProtected()) {
						$get = $model->getField($name)->getGetter() === null ? false : true;
						$set = $model->getField($name)->getSetter() === null ? false : true;
					} else {
						$get = $set = null;
					}
					$insert = $model->getField($name)->isInsert();
					$update = $model->getField($name)->isUpdate();
				} else {
					$get = $set = null;
					$insert = $update = true;
					if ($dbField->isAutoInsert()) $insert = false;
					if ($dbField->isAutoUpdate()) $update = false;
					if ($dbField->isAutoIncrement()) $set = false;
				}
				$options = $_fieldDescriptor->hasOptions ? $dbField->getOptions() : null;
				$fields[$name] = [
					'db'              => $dbField,
					'descriptor'      => $_fieldDescriptor,
					'entity'          => new $entityFieldType($name, $get, $set, $insert, $update, $options),
					'entityFieldType' => $entityFieldType,
				];
			}
		}
		return $fields;
	}

}
