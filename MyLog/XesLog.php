<?php

namespace Xes\Service\Log;

include_once __DIR__ . '/LogManager.php';

class Logger;
{

    const DEBUG     = 100;
    const INFO      = 200;
    const NOTICE    = 250;
    const WARNING   = 300;
    const ERROR     = 400;
    const CRITICAL  = 500;
    const ALERT     = 550;
    const EMERGENCY = 600;

	static public function __callStatic($name, $params)
	{
		$instance = LogManager::getInstance();

		return $instance->$name(...$params);
	}
}
