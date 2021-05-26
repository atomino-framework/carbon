<?php namespace Atomino\Carbon\Generator;

class CodeWriter{
	private array $code = [];
	private array $annotation = [];
	private array $interface = [];
	private array $attribute = [];

	public function addAnnotation(string $value){ $this->annotation[] = $value; }
	public function addCode(string $value){ $this->code[] = $value; }
	public function addInterface(string $value){ $this->interface[] = $value; }
	public function addAttribute(string $value){ $this->attribute[] = $value; }

	public function getCode(): string{
		return join("\n", array_map(function ($line){ return "\t" . trim($line); }, $this->code));
	}

	public function getAnnotation(): string{
		return join("\n", array_map(function ($line){ return " * " . trim($line); }, $this->annotation));
	}

	public function getAttribute(): string{
		return join("\n", array_map(function ($line){ return  trim($line); }, $this->attribute));
	}

	public function getInterface(): string{
		if(count($this->interface)) return 'implements '.join(', ', array_map(function($i){return '\\'.trim($i, '\\');},$this->interface));
		else return '';
	}


}