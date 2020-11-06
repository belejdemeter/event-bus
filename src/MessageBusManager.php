<?php

namespace Core\Messaging;

use Core\Messaging\Providers\AwsProvider;
use Core\Messaging\Providers\RabbitMqProvider;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class MessageBusManager extends Manager
{
    /**
     * @return RabbitMqProvider
     */
    protected function createRabbitMqDriver()
    {
        $service_id = $this->config->get('service_bus.service_id');
        $config = [
            'host'       => $this->config->get('service_bus.rabbit_mq.host'),
            'port'       => $this->config->get('service_bus.rabbit_mq.port'),
            'login'      => $this->config->get('service_bus.rabbit_mq.login'),
            'password'   => $this->config->get('service_bus.rabbit_mq.password'),
            'vhost'      => $this->config->get('service_bus.rabbit_mq.vhost'),
            'service_id' => $this->config->get('service_bus.rabbit_mq.service_id'),
        ];
        return new RabbitMqProvider($config, $service_id);
    }

    /**
     * @return AwsProvider
     */
    protected function createAwsDriver()
    {
        $service_id = $this->config->get('service_bus.service_id');
        $config = [
            'account_id' => $this->config->get('service_bus.aws.account_id'),
            'credentials' => $this->config->get('service_bus.aws.credentials'),
            'region' =>  $this->config->get('service_bus.aws.region'),
            'version' =>  $this->config->get('service_bus.aws.version'),
        ];
        return new AwsProvider($config, $service_id);
    }

    /**
     * @return string|void
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('Driver not set.');
    }
}