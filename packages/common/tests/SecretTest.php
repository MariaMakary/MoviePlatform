<?php

namespace MoviePlatform\Common\Tests;

use PHPUnit\Framework\TestCase;

class SecretTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'secrets_'.uniqid();
        mkdir($this->dir);
        $_SERVER['SECRETS_DIR'] = $this->dir;
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.DIRECTORY_SEPARATOR.'*'));
        rmdir($this->dir);
        unset($_SERVER['SECRETS_DIR'], $_SERVER['TEST_SECRET']);

        parent::tearDown();
    }

    public function test_returns_trimmed_file_content(): void
    {
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'test_secret', "  from-file\r\n");
        $_SERVER['TEST_SECRET'] = 'from-env';

        $this->assertSame('from-file', secret('test_secret'));
    }

    public function test_falls_back_to_env_when_file_is_missing(): void
    {
        $_SERVER['TEST_SECRET'] = 'from-env';

        $this->assertSame('from-env', secret('test_secret'));
    }

    public function test_falls_back_to_env_when_file_is_empty(): void
    {
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'test_secret', "\n");
        $_SERVER['TEST_SECRET'] = 'from-env';

        $this->assertSame('from-env', secret('test_secret'));
    }

    public function test_returns_default_when_nothing_is_set(): void
    {
        $this->assertSame('fallback', secret('test_secret', 'fallback'));
    }

    public function test_resolves_a_relative_secrets_dir_from_the_project_folder(): void
    {
        $project = $this->dir.DIRECTORY_SEPARATOR.'project';
        mkdir($project);
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'test_secret', 'from-repo-secrets');
        $_SERVER['SECRETS_DIR'] = '..';
        $cwd = getcwd();
        chdir($project);

        try {
            $this->assertSame('from-repo-secrets', secret('test_secret'));
        } finally {
            chdir($cwd);
            rmdir($project);
        }
    }
}
