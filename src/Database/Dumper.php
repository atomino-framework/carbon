<?php namespace Atomino\Carbon\Database;

use Rah\Danpu\Dump;
use Rah\Danpu\Export;

class Dumper{

	protected Dump $dumper;

	public function __construct(Connection $connection, private string $path, string $tmp){
		preg_match('/password=(?<password>.*?)(;|$)/', $connection->getDsn(), $match);
		$password = $match['password'];
		preg_match('/user=(?<user>.*?)(;|$)/', $connection->getDsn(), $match);
		$user = $match['user'];
		$this->dumper = ( new Dump() )
			->dsn($connection->getDsn())
			->pass($password)
			->user($user)
			->tmp($tmp);
	}

	public function dump($file){
		$this->dumper->structure(true)->disableForeignKeyChecks(true)->data(true)->file($this->path .'/'. $file);
		( new Export($this->dumper) );
	}

	public function structure($file){
		$this->dumper->structure(true)->disableForeignKeyChecks(true)->data(false)->file($this->path .'/'. $file);
		( new Export($this->dumper) );
	}

	public function data($file){
		$this->dumper->structure(false)->disableForeignKeyChecks(true)->data(true)->file($this->path .'/'. $file);
		( new Export($this->dumper) );
	}

//	public function getDumper(): Dump{ return $this->dumper; }

}
