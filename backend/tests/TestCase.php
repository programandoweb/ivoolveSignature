<?php

namespace Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = storage_path('framework/testing/views/'.str_replace('\\', '_', static::class).'_'.spl_object_id($this));

        config()->set('view.compiled', $compiledPath);
        File::deleteDirectory($compiledPath);
        File::ensureDirectoryExists($compiledPath);
    }
}
