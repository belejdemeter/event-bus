<?php

namespace Core\Messaging\Providers;

use Core\Contracts\Consumer;
use Core\Contracts\Publisher;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;

class RabbitMqProvider implements Publisher, Consumer
{

    /** @var string */
    private $service_id;

    /** @var AMQPStreamConnection */
    private $connection;

    /** @var AMQPChannel */
    private $channel;

    /**
     * Create new instance.
     * @param array $config
     * @param string $service_id
     */
    public function __construct(array $config, string $service_id)
    {
        $this->service_id = $service_id;
        $this->connect($config);
    }

    /**
     * @param string $str_message
     * @return mixed|void
     * @throws Exception
     */
    public function publish(string $str_message)
    {
        $message = new AMQPMessage($str_message, [
            'correlation_id' => (string)Uuid::uuid4(),
            'content_type' => 'application/json'
        ]);
        $this->publishMessage($message);
        $this->close();

        return $message;
    }

    /**
     * @param callable $callback
     * @return mixed|void
     */
    public function consume(callable $callback)
    {
        $this->makeConsumer(function(AMQPMessage $msg) use ($callback) {
            return $callback($msg->getBody());
        });
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
        $this->close();
    }

    /**
     * We create a connection to the server.
     * @param array $config
     * @return void
     */
    protected function connect(array $config)
    {
        $host = $config['host'];
        $port = $config['port'];
        $login = $config['login'];
        $password = $config['password'];
        $vhost = $config['vhost'];

        $this->connection = new AMQPStreamConnection($host, $port, $login, $password, $vhost);
        $this->channel = $this->connection->channel();
    }

    /**
     * We close the channel and the connection;
     * @return void
     */
    protected function close()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @param AMQPMessage $msg
     * @param void
     */
    protected function publishMessage(AMQPMessage $msg)
    {
        $exchange = 'events';
        $routing_key = $this->service_id;
        $mandatory = false;
        $immediate = false;
        $ticket = null;
        $this->channel->basic_publish($msg, $exchange, $routing_key, $mandatory, $immediate, $ticket);
    }

    /**
     * @param $callback
     * @return mixed|string
     */
    protected function makeConsumer($callback)
    {
        $queue = 'evt_' . $this->service_id;
        $consumer_tag = $this->service_id;
        $no_local = false;
        $no_ack = true;
        $exclusive = false;
        $nowait = false;
        $ticket = null;
        $arguments = [];
        return $this->channel->basic_consume($queue, $consumer_tag, $no_local, $no_ack, $exclusive, $nowait, $callback, $ticket, $arguments);
    }
}