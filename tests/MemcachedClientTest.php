<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use MemcachedClient\MemcachedClient;

class MemcachedClientTest extends TestCase
{
    private MemcachedClient $client;

    protected function setUp(): void
    {
        $this->client = new MemcachedClient('127.0.0.1', 11211);
    }

    public function testSetAndGet()
    {
        $this->assertTrue($this->client->set('key', 'value'));
        $this->assertEquals('value', $this->client->get('key'));
    }

    public function testSetWithExpiration()
    {
        $this->assertTrue($this->client->set('key', 'value', 1));
        sleep(2);
        $this->assertNull($this->client->get('key'));
    }

    public function testSetWithFlags()
    {
        $this->assertTrue($this->client->set('key', 'value', 0, 1));
        $this->assertEquals('value', $this->client->get('key'));
    }

    public function testSetWithLargeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is too large');
        $value = str_repeat('a', MemcachedClient::MAX_VALUE_SIZE + 1);
        $this->client->set('key', $value);
    }

    public function testSetWithSpacesInKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key should not contain spaces');
        $this->client->set('key with spaces', 'value');
    }

    public function testGetNonexistentKey()
    {
        $this->assertNull($this->client->get('nonexistent_key'));
    }

    public function testDelete()
    {
        $this->client->set('key', 'value');
        $this->assertTrue($this->client->delete('key'));
        $this->assertNull($this->client->get('key'));
    }

    public function testDeleteNonexistentKey()
    {
        $this->assertFalse($this->client->delete('nonexistent_key'));
    }

    public function testDeleteWithSpacesInKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key should not contain spaces');
        $this->client->delete('key with spaces');
    }

    protected function tearDown(): void
    {
        $this->client->delete('key');
    }
}
