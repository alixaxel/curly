<?php

use alixaxel\curly\CURL;

class CURL_Test extends PHPUnit_Framework_TestCase
{
	private static $url = 'http://httpbin.org/delay/3';
	
	public function test_Uni()
	{
		$this->assertTrue(CURL::Uni(self::$url, null, 'GET', null, null, 3) !== false);
	}
	
	public function test_Multi()
	{
		$handles = [];
		
		for ($i = 0; $i <= 16; ++$i)
		{
			$handles[] = CURL::Uni(self::$url, null, 'GET', null, null, 0);
		}
		
		$this->assertTrue(in_array(false, CURL::Multi($handles, null, 4, null), true) === false);
	}
}
