<?php

/**
 * Scheduler implementation module
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Scheduler\Module;

use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\MessagesRouter\ChainRouterConfigurator;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Scheduler\Emitter\RabbitMQEmitter;
use ServiceBus\Scheduler\Emitter\SchedulerEmitter;
use ServiceBus\Scheduler\SchedulerProvider;
use ServiceBus\Scheduler\Store\SchedulerStore;
use ServiceBus\Scheduler\Store\SqlSchedulerStore;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 */
final class SchedulerModule implements ServiceBusModule
{
    private const TYPE = 'rabbitmq';

    /**
     * @var string
     */
    private $adapterType;

    /**
     * @var string
     */
    private $storeImplementationServiceId;

    /**
     * @var string
     */
    private $databaseAdapterServiceId;

    /**
     * @param string $databaseAdapterServiceId
     *
     * @return self
     */
    public static function rabbitMqWithSqlStorage(string $databaseAdapterServiceId): self
    {
        return new self(
            self::TYPE,
            SqlSchedulerStore::class,
            $databaseAdapterServiceId
        );
    }

    /**
     * @inheritDoc
     */
    public function boot(ContainerBuilder $containerBuilder): void
    {
        $this->registerSchedulerStore($containerBuilder);
        $this->registerSchedulerProvider($containerBuilder);
        $this->registerEmitter($containerBuilder);
        $this->registerSchedulerMessagesRouterConfigurator($containerBuilder);

        $routerConfiguratorDefinition = $this->getRouterConfiguratorDefinition($containerBuilder);

        /** @noinspection PhpUnhandledExceptionInspection */
        $routerConfiguratorDefinition->addMethodCall(
            'addConfigurator',
            [new Reference(SchedulerMessagesRouterConfigurator::class)]
        );
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @return Definition
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function getRouterConfiguratorDefinition(ContainerBuilder $containerBuilder): Definition
    {
        if(false === $containerBuilder->hasDefinition(ChainRouterConfigurator::class))
        {
            $containerBuilder->addDefinitions([
                    ChainRouterConfigurator::class => new Definition(ChainRouterConfigurator::class)
                ]
            );
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $routerConfiguratorDefinition = $containerBuilder->getDefinition(ChainRouterConfigurator::class);

        if(false === $containerBuilder->hasDefinition(Router::class))
        {
            $containerBuilder->addDefinitions([Router::class => new Definition(Router::class)]);
        }

        /** @var Definition $routerConfiguratorDefinition */

        return $routerConfiguratorDefinition;
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @return void
     */
    private function registerSchedulerMessagesRouterConfigurator(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            SchedulerMessagesRouterConfigurator::class => (new Definition(SchedulerMessagesRouterConfigurator::class))
                ->setArguments([new Reference(SchedulerEmitter::class)])
        ]);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @return void
     *
     * @throws \LogicException
     */
    private function registerEmitter(ContainerBuilder $containerBuilder): void
    {
        if(self::TYPE === $this->adapterType)
        {
            $containerBuilder->addDefinitions([
                SchedulerEmitter::class => (new Definition(RabbitMQEmitter::class))
                    ->setArguments([new Reference(SchedulerStore::class)])
            ]);

            return;
        }

        throw new \LogicException('Wrong adapter type');
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @return void
     */
    private function registerSchedulerStore(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            SchedulerStore::class => (new Definition($this->storeImplementationServiceId))
                ->setArguments([new Reference($this->databaseAdapterServiceId)])
        ]);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @return void
     */
    private function registerSchedulerProvider(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            SchedulerProvider::class => (new Definition(SchedulerProvider::class))
                ->setArguments([new Reference(SchedulerStore::class)])
        ]);
    }

    /**
     * @param string $adapterType
     * @param string $storeImplementationServiceId
     * @param string $databaseAdapterServiceId
     */
    private function __construct(
        string $adapterType,
        string $storeImplementationServiceId,
        string $databaseAdapterServiceId
    )
    {
        $this->adapterType                  = $adapterType;
        $this->storeImplementationServiceId = $storeImplementationServiceId;
        $this->databaseAdapterServiceId     = $databaseAdapterServiceId;
    }
}
