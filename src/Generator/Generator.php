<?php namespace Atomino\Carbon\Generator;

use Atomino\Carbon\Attributes\BelongsTo;
use Atomino\Carbon\Attributes\BelongsToMany;
use Atomino\Carbon\Attributes\HasMany;
use Atomino\Carbon\Attributes\RequiredField;
use Atomino\Carbon\Attributes\Virtual;
use Atomino\Carbon\Database\Descriptor;
use Atomino\Carbon\Field\Attributes\FieldDescriptor;
use Atomino\Carbon\Model;
use Atomino\Carbon\Plugin\Plugin;
use Atomino\Core\Cli\Style;
use Atomino\Neutrons\CodeFinder;
use CaseHelper\CamelCaseHelper;
use CaseHelper\SnakeCaseHelper;
use Composer\Autoload\ClassLoader;
use Riimu\Kit\PHPEncoder\PHPEncoder;
use function Atomino\dic;
use function Atomino\path;

class Generator {

	const ATOM_SHADOW_ENTITY_NS = 'Atomino\Atoms\Entity';
	const ATOM_ENTITY_FINDER_NS = 'Atomino\Atoms\EntityFinder';

	private CodeFinder $codeFinder;
	private PHPEncoder $encoder;
	private string $entityPath;
	private string $shadowPath;
	private string $finderPath;

	public function __construct(private string $namespace, private Style $style) {
		$this->codeFinder = new CodeFinder(dic()->get(ClassLoader::class));
		$this->encoder = new PHPEncoder();
		$this->entityPath = substr(realpath($this->codeFinder->Psr4ResolveNamespace($this->namespace)), strlen(path()));
		$this->shadowPath = substr(realpath($this->codeFinder->Psr4ResolveNamespace(static::ATOM_SHADOW_ENTITY_NS)), strlen(path()));
		$this->finderPath = substr(realpath($this->codeFinder->Psr4ResolveNamespace(static::ATOM_ENTITY_FINDER_NS)), strlen(path()));
	}

	public function create(string $name) {
		$table = (new CamelCaseHelper())->toSnakeCase($name);
		$class = ucfirst((new SnakeCaseHelper())->toCamelCase($table));

		$translate = [
			"{{name}}"             => $class,
			"{{table}}"            => $table,
			"{{entity-namespace}}" => $this->namespace,
			"{{shadow-namespace}}" => static::ATOM_SHADOW_ENTITY_NS,
			"{{finder-namespace}}" => static::ATOM_ENTITY_FINDER_NS,
			"#:code"               => '',
			"#:annotation"         => '',
			"#:attribute"          => '',
			"{{interface}}"        => '',
		];

		$files = [
			"entity.txt" => "{$this->entityPath}/{$class}.php",
			"shadow.txt" => "{$this->shadowPath}/_{$class}.php",
			"finder.txt" => "{$this->finderPath}/_{$class}.php",
		];

		$this->style->_section('Create base entity "' . $class . '"');

		foreach ($files as $templateFile => $file) {

			$this->style->_task($file);
			$file = path($file);

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

		$errors = [];

		$style = $this->style;

		$modified = false;

		$entities = $this->codeFinder->Psr4ClassSeeker($this->namespace);

		/** @var \Atomino\Carbon\Entity $entity */
		foreach ($entities as $entity) {

			$ENTITY = new \ReflectionClass($entity);
			$class = $ENTITY->getShortName();
			$style->_section($class);
			$model = new Model($entity);

			$style->_task('Fetching table info: ' . $model->getTable());
			$table = $model->getConnection()->getDescriptor()->getTable($model->getTable());

			if (is_null($table)) {
				$errors[] = [ $class, $model->getTable().' table does not exists!'];
				$style->_task_error('Table does not exists!');
				continue;
			}

			if ($table->isView() && $model->isMutable()){
				$style->_task_warn('Storage is a VIEW. Entity should be immutable!', false);
				$errors[] = [$class, $model->getTable().' Storage is a VIEW. Entity should be immutable!'];

			}
			else $style->_task_ok();

			$fields = $this->fetchFields($model, $table, $errors);

			$translate = [
				"{{name}}"             => $class,
				"{{table}}"            => $model->getTable(),
				"{{entity-namespace}}" => $this->namespace,
				"{{shadow-namespace}}" => static::ATOM_SHADOW_ENTITY_NS,
				"{{finder-namespace}}" => static::ATOM_ENTITY_FINDER_NS,
				"#:code"               => '',
				"#:annotation"         => '',
				"#:attribute"          => '',
				"{{interface}}"        => '',
			];

			$cw = new CodeWriter();


			#region plugins
			foreach ($ENTITY->getAttributes(Plugin::class, \ReflectionAttribute::IS_INSTANCEOF) as $Plugin) {
				$instance = $Plugin->newInstance();
				if (!is_null($trait = $instance->getTrait())) {
					$cw->addCode('use \\' . trim($trait, '\\') . ';');
				}
				$instance->generate($ENTITY, $cw);
			}
			#endregion


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


			#region check-required-fields
			$Requireds = RequiredField::all($ENTITY, $ENTITY->getParentClass(), ...$ENTITY->getTraits(), ...$ENTITY->getParentClass()->getTraits());
			foreach ($Requireds as $Required) {
				$style->_task('Required field: ' . $Required->field);
				if (!array_key_exists($Required->field, $fields)) {
					$style->_task_error('missing');
					$errors[] = [$class, 'Required field missing: ' . $Required->field];
				} elseif ($fields[$Required->field]['entityFieldType'] !== $Required->type) {
					$style->_task_error('type mismatch (' . $Required->type . ')');
					$errors[] = [$class, 'Required type mismatch (' . $Required->type . '): ' . $Required->field];
				} else {
					$style->_task_ok();
				}
			}
			#endregion


			#region relations
			foreach ($model->getRelations() as $relation) {
				if ($relation instanceof BelongsTo) {
					$cw->addAnnotation("@property-read \\" . $relation->entity . " $" . $relation->target);
				}
				if ($relation instanceof HasMany) {
					$cw->addAnnotation("@property-read \\Atomino\\Atoms\\EntityFinder\\_" . (new \ReflectionClass($relation->entity))->getShortName() . " $" . $relation->target);
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
				if ($virtual->set) $cw->addCode("abstract protected function set" . ucfirst($virtual->field) . "(" . $virtual->type . " \$value):" . ";");
			}
			#endregion


			$translate['#:code'] = $cw->getCode();
			$translate['#:attribute'] = $cw->getAttribute();
			$translate['#:annotation'] = $cw->getAnnotation();
			$translate['{{interface}}'] = $cw->getInterface();

			$style->_task("{$this->shadowPath}/_{$class}.php");
			$template = file_get_contents(__DIR__ . '/$resources/shadow.txt');
			$template = strtr($template, $translate);
			$outfile = path("{$this->shadowPath}/_{$class}.php");

			if (!file_exists($outfile)) {
				file_put_contents($outfile, $template);
				$style->_task_ok('created');
			} elseif (file_get_contents($outfile) !== $template) {
				$modified = true;
				file_put_contents($outfile, $template);
				$style->_task_ok('modified');
			} else {
				$style->_task_ok();
			}
		}

		if(count($errors)){
			$style->_section("Errors");

			foreach ($errors as $error){
				$style->_task($error[0]);
				$style->_task_error($error[1]);
			}
		}

		if ($modified) {
			$style->newLine();
			$style->_warn('Rerun generator!');
			return 1;
		}
		return 0;
	}

	private function fetchFields(Model $model, Descriptor\Table $table, &$errors): array {
		$style = $this->style;

		$fields = [];

		foreach ($table->getFields() as $name => $dbField) {
			$style->_task($name);

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
				$style->_task_error('unsupported type: ' . $dbField->getTypeString());
				$errors[] = [$table->getName(), 'Unsupported type: ' . $dbField->getTypeString()];
			} else {
				$_fieldDescriptor = FieldDescriptor::get(new \ReflectionClass($entityFieldType));

				$style->_task_ok($dbField->getTypeString());
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