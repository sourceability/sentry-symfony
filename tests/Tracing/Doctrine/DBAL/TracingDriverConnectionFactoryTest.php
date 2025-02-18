<?php

declare(strict_types=1);

namespace Sentry\SentryBundle\Tests\Tracing\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use Sentry\SentryBundle\Tests\DoctrineTestCase;
use Sentry\SentryBundle\Tests\Tracing\Doctrine\DBAL\Fixture\ServerInfoAwareConnectionStub;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverConnection;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverConnectionFactory;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingServerInfoAwareDriverConnection;
use Sentry\State\HubInterface;

final class TracingDriverConnectionFactoryTest extends DoctrineTestCase
{
    /**
     * @var MockObject&HubInterface
     */
    private $hub;

    /**
     * @var MockObject&AbstractPlatform
     */
    private $databasePlatform;

    /**
     * @var TracingDriverConnectionFactory
     */
    private $tracingDriverConnectionFactory;

    public static function setUpBeforeClass(): void
    {
        if (!self::isDoctrineDBALInstalled()) {
            self::markTestSkipped('This test requires the "doctrine/dbal" Composer package.');
        }
    }

    protected function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
        $this->databasePlatform = $this->createMock(AbstractPlatform::class);
        $this->tracingDriverConnectionFactory = new TracingDriverConnectionFactory($this->hub);
    }

    /**
     * @dataProvider createDataProvider
     *
     * @param class-string<AbstractPlatform> $databasePlatformFqcn
     */
    public function testCreate(string $databasePlatformFqcn, string $expectedDatabasePlatform): void
    {
        $connection = $this->createMock(Connection::class);
        $databasePlatform = $this->createMock($databasePlatformFqcn);
        $driverConnection = $this->tracingDriverConnectionFactory->create($connection, $databasePlatform, []);
        $expectedDriverConnection = new TracingDriverConnection($this->hub, $connection, $expectedDatabasePlatform, []);

        $this->assertEquals($expectedDriverConnection, $driverConnection);
    }

    public static function createDataProvider(): \Generator
    {
        yield [
            AbstractMySQLPlatform::class,
            'mysql',
        ];

        yield [
            DB2Platform::class,
            'db2',
        ];

        yield [
            OraclePlatform::class,
            'oracle',
        ];

        yield [
            PostgreSQLPlatform::class,
            'postgresql',
        ];

        yield [
            SqlitePlatform::class,
            'sqlite',
        ];

        yield [
            SQLServerPlatform::class,
            'mssql',
        ];

        yield [
            AbstractPlatform::class,
            'other_sql',
        ];
    }

    public function testCreateWithServerInfoAwareConnection(): void
    {
        if (!self::isDoctrineDBALVersion3Installed()) {
            self::markTestSkipped('This test requires the version of the "doctrine/dbal" Composer package to be >= 3.0.');
        }

        $connection = $this->createMock(ServerInfoAwareConnectionStub::class);
        $driverConnection = $this->tracingDriverConnectionFactory->create($connection, $this->databasePlatform, []);
        $expectedDriverConnection = new TracingServerInfoAwareDriverConnection(new TracingDriverConnection($this->hub, $connection, 'other_sql', []));

        $this->assertEquals($expectedDriverConnection, $driverConnection);
    }
}
