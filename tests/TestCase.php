<?php

namespace Rafik\PscWorldWebservice\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	protected function loadResource(string $resourcePath): string
	{
		return file_get_contents($this->getResourceDir() . DIRECTORY_SEPARATOR . $resourcePath);
	}

	protected function getResourceDir(): string
	{
		return __DIR__ . DIRECTORY_SEPARATOR . 'resources';
	}
}