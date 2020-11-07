<?php

namespace Core\Messaging\Providers;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Core\Contracts\Consumer;
use Core\Contracts\Publisher;
use Exception;
use Illuminate\Support\Arr;
use Ramsey\Uuid\Uuid;

class AwsProvider implements Publisher, Consumer
{

    /** @var string */
    private $service_id;

    /** @var string */
    private $aws_region;

    /** @var string */
    private $aws_account_id;

    /** @var SnsClient */
    private $sns;

    /** @var SqsClient */
    private $sqs;

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
        $payload = array(
            'TopicArn' => $this->getTopicArn(),
            'Message' => $str_message,
            'MessageStructure' => 'string',
            'MessageGroupId' => 'events-'.$this->service_id,
            'MessageDeduplicationId' => (string)Uuid::uuid4(),
        );
        return $this->sns->publish($payload);
    }

    /**
     * @param callable $callback
     * @return mixed|void
     */
    public function consume(callable $callback)
    {
        $queueUrl = $this->getQueueUrl();
        while (true) {
            $result = $this->sqs->receiveMessage([
                'AttributeNames' => ['SentTimestamp'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => $queueUrl,
                'WaitTimeSeconds' => 20, // 0-20
            ]);
            $messages = $result->get('Messages');

            if (!empty($messages)) {
                foreach ($messages as $message) {

                    $callback($message["Body"]);

                    $this->sqs->deleteMessage([
                        'QueueUrl' => $queueUrl,
                        'ReceiptHandle' => $message['ReceiptHandle']
                    ]);
                }
            } else {
                // No messages in queue.
                sleep(20);
            }
        }
    }

    /**
     * We create a connection to the server.
     * @param array $config
     * @return void
     */
    protected function connect(array $config)
    {
        $this->aws_account_id = $config['account_id'];
        $this->aws_region = $config['region'];
        $client_config = Arr::only($config, ['credentials', 'region', 'version']);

        $this->sqs = new SqsClient($client_config);
        $this->sns = new SnsClient($client_config);
    }

    /**
     * We close the channel and the connection;
     * @return void
     */
    protected function close()
    {
        // ...
    }

    /**
     * Get the topic's ARN. The ARN format is arn:partition:service:region:account-id:resource-id
     * @return string
     */
    protected function getTopicArn()
    {
        $region = $this->aws_region;
        $account_id = $this->aws_account_id;
        $resource_id = $this->service_id;
        return "arn:aws:sns:$region:$account_id:$resource_id.fifo";
    }

    /**
     * Get the queue's URL address
     * @return string
     */
    protected function getQueueUrl()
    {
        $region = $this->aws_region;
        $account_id = $this->aws_account_id;
        $resource_id = $this->service_id;
        return "https://sqs.$region.amazonaws.com/$account_id/$resource_id.fifo";
    }

    /**
     * Get this queue's ARN. The ARN format is arn:partition:service:region:account-id:resource-id
     * @return string
     */
    protected function getQueueArn()
    {
        $region = $this->aws_region;
        $account_id = $this->aws_account_id;
        $resource_id = $this->service_id;
        return "arn:aws:sqs:$region:$account_id:$resource_id";
    }
}