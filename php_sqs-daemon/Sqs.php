mespace Ocono\Daemon\Aws;

use Aws\Sqs\SqsClient;
use Ocono\Daemon\Communication\RequestHandler;
use Ocono\Daemon\Communication\RequestTransformer;
use Ocono\Daemon\Communication\RequestTransformerException;
use Ocono\Daemon\Helper\Logger;
use Ocono\Daemon\Helper\Tries;
use Registry;

/**
 * Class Sqs
 * @package Ocono\Daemon\Aws\Sqs
 */
class Sqs implements SqsInterface
{
    /**
     * @var SqsClient
     */
    protected $client;

    /**
     * Sqs constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        $this->client = new SqsClient($config);
    }

    /**
     * Read from queue
     * @param string $queueUrl
     * @return \Closure
     */
    public function getMessages($queueUrl)
    {
        $sqsClient = $this->client;

        /**
         * @return array | null
         */
        $closure = function () use ($sqsClient, $queueUrl) {
            try {
                $result = $sqsClient->receiveMessage([
                    'QueueUrl' => $queueUrl,
                    'MaxNumberOfMessages' => 10 // values supported [1,10]
                ]);

                if ($messages = $result->get('Messages')) {
                    return $messages;
                }

            } catch (\Exception $e) {

                Logger::info('Running Tries::getResult', 'Sqs::getMessages. ' . $e->getMessage());
            }

            return null;
        };

        return $closure;
    }

    /**
     * @param string $queueHandle
     * @param string $queueUrl
     * @return \Closure
     */
    public function deleteMessage($queueHandle, $queueUrl)
    {
        $sqsClient = $this->client;

        /**
         * @return bool
         */
        $closure = function () use ($sqsClient, $queueUrl, $queueHandle) {
            try {
                $result = $sqsClient->deleteMessage([
                    'QueueUrl' => $queueUrl,
                    'ReceiptHandle' => $queueHandle
                ]);

                if ($result->get('@metadata')['statusCode'] === 200) {
                    return true;
                }

            } catch (\Exception $e) {

                Logger::info('Running Tries::getResult', 'Sqs::deleteMessage. ' . $e->getMessage());
            }

            return false;
        };

        return $closure;
    }

    /**
     * @param string $message - JSON
     * @param string $queueUrl
     * @return \Closure
     */
    public function sendMessage($message, $queueUrl)
    {
        $sqsClient = $this->client;

        /**
         * @return bool
         */
        $closure = function () use ($message, $queueUrl, $sqsClient) {
            try {
                $result = $sqsClient->sendMessage([
                    'QueueUrl' => $queueUrl,
                    'MessageBody' => $message
                ]);

                if ($result->get('@metadata')['statusCode'] === 200) {
                    return true;
                }

            } catch (\Exception $e) {

                Logger::info('Running Tries::getResult', 'Sqs::sendMessage. ' . $e->getMessage());
            }

            return false;
        };

        return $closure;
    }

    /**
     * @param string $message - JSON
     * @param string $rejectedQueueUrl - fallback queue for rejected messages
     * @return \Closure
     */
    public function httpSendMessage($message, $rejectedQueueUrl)
    {
        /**
         * @return bool
         */
        $closure = function () use ($message, $rejectedQueueUrl) {
            try {
                $request = RequestTransformer::messageToRequest($message);

                /**
                 * @var RequestHandler $httpClient
                 */
                $httpClient = Registry::getInstance()->get(RequestHandler::class);
                $response = $httpClient->send($request);

                if ($response !== null) {
                    return true;
                }

            } catch (RequestTransformerException $e) { // message is not Psr7 compatible

                $sendMessageTries = new Tries($this->sendMessage($message, $rejectedQueueUrl), 2, 'Could not send HTTP Request.');

                try {
                    return $sendMessageTries->getResult();
                } catch (\RuntimeException $e) {
                    Logger::info($e->getMessage());
                }

            }

            return false;
        };

        return $closure;
    }
}

