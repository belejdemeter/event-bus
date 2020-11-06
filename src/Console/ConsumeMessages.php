<?php 

namespace Core\Messaging\Console;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Core\Contracts\Consumer;
use Core\EventSourcing\DomainEvent;
use Core\EventSourcing\Contracts\EventDispatcher;
use Exception;


class ConsumeMessages extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'mq:server';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Listen to a queue.';

    /**
     * Consumer instance.
     * @var Consumer
     */
    protected $consumer;

    /**
     * Event dispatcher instance.
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * Create a new command instance.
     * @param Consumer $consumer
     * @param EventDispatcher $dispatcher
     */
    public function __construct(Consumer $consumer, EventDispatcher $dispatcher)
    {
    	parent::__construct();
        $this->consumer = $consumer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Execute the console command.
     * @return mixed
     * @throws Exception
     */
    public function handle()
    {
        $process_id = getmypid();
        $tz = new DateTimeZone(env('APP_TIMEZONE', 'UTC'));
        $timestamp = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
        $this->info("Starting consumer process $process_id at $timestamp");
        $this->consumer->consume(function(string $message) {
            $event = $this->mapMessageToEvent($message);
            $this->dispatcher->dispatch($event);
        });
    }

    /**
     * @param string $message_str
     * @return DomainEvent
     */
    protected function mapMessageToEvent(string $message_str)
    {
        $payload = json_decode($message_str, true);
        return new DomainEvent($payload);
    }
}