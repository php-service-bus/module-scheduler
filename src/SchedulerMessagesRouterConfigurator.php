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

use ServiceBus\MessagesRouter\Exceptions\MessageRouterConfigurationFailed;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\MessagesRouter\RouterConfigurator;
use /** @noinspection PhpInternalEntityUsedInspection */
    ServiceBus\Scheduler\Contract\EmitSchedulerOperation;
use /** @noinspection PhpInternalEntityUsedInspection */
    ServiceBus\Scheduler\Contract\OperationScheduled;
use /** @noinspection PhpInternalEntityUsedInspection */
    ServiceBus\Scheduler\Contract\SchedulerOperationCanceled;
use /** @noinspection PhpInternalEntityUsedInspection */
    ServiceBus\Scheduler\Contract\SchedulerOperationEmitted;
use ServiceBus\Scheduler\Emitter\SchedulerEmitter;
use ServiceBus\Scheduler\Processor\SchedulerMessagesProcessor;

/**
 *
 */
final class SchedulerMessagesRouterConfigurator implements RouterConfigurator
{
    /**
     * @var SchedulerEmitter
     */
    private $emitter;

    /**
     * @param SchedulerEmitter $emitter
     */
    public function __construct(SchedulerEmitter $emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * @inheritDoc
     */
    public function configure(Router $router): void
    {
        try
        {
            $processor = new SchedulerMessagesProcessor($this->emitter);

            /** @noinspection PhpInternalEntityUsedInspection */
            $listenEvents = [
                SchedulerOperationEmitted::class,
                SchedulerOperationCanceled::class,
                OperationScheduled::class
            ];

            foreach($listenEvents as $event)
            {
                $router->registerListener($event, $processor);
            }

            /** @noinspection PhpInternalEntityUsedInspection */
            $router->registerHandler(EmitSchedulerOperation::class, $processor);

        }
        catch(\Throwable $throwable)
        {
            throw new MessageRouterConfigurationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }
}
