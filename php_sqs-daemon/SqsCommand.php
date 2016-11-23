mespace Ocono\Daemon\Process;

use Ocono\Daemon\Daemon;
use Ocono\Daemon\Helper\Aggregator;
use Ocono\Daemon\Helper\Config;
use Ocono\Daemon\Command\ReadQueue;
use Ocono\Daemon\Command\InputOptionsDefinition;
use Ocono\Daemon\Aws\Sqs;
use Ocono\Daemon\Helper\Logger;
use Ocono\Daemon\Helper\Tries;
use Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SqsCommand
 * @package Ocono\Daemon\Process
 */
class SqsCommand extends Command
{
    /**
     * Command configuration
     */
    protected function configure()
    {
        $config = Config::get('command.sqs-daemon');

        $commandName = $config['command-name'];
        $commandDescription = $config['command-description'];
        $inputOptions = InputOptionsDefinition::getInputDefinition($config['input-options']);

        $this
            ->setName($commandName)
            ->setDescription($commandDescription)
            ->setDefinition($inputOptions);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var Sqs $sqsClient
         */
        $sqsClient = Registry::getInstance()->get(Sqs::class);

        $aggregator = new Aggregator($sqsClient);

        $readQueue = new ReadQueue($input->getOptions());

        $queueUrl = $readQueue->getQueueUrl();
        $dlQueueUrl = $readQueue->getDlQueueUrl();

        /**
         * @var Daemon $daemon
         */
        $daemon = $this->getApplication();

        $daemon->loop(
            function () use ($readQueue, $sqsClient, $queueUrl, $dlQueueUrl, $aggregator) {
                try {
                    $loadMessagesTries = new Tries($sqsClient->getMessages($queueUrl), 2, 'Could not load SQS messages');

                    try{
                    $messages = $loadMessagesTries->getResult();
                    } catch(\RuntimeException $e){

                        usleep(Config::get('config.empty-queue-sleep-time-ms') * 1000);

                        throw $e;

                    }

                    if (!empty($messages)) {
                        foreach ($messages as $key => $message) {

                            $persistTries = new Tries($aggregator->getPersistAggregator($message['Body'], $dlQueueUrl), 3, 'Could not send HTTP request');
                            $persistSuccess = $persistTries->getResult();

                            // remove processed
                            if ($persistSuccess === true) {
                                $deleteTries = new Tries($sqsClient->deleteMessage($message['ReceiptHandle'], $queueUrl), 2, 'Could not delete SQS message');
                                $deleteSuccess = $deleteTries->getResult();

                                // remove from pending message list
                                if ($deleteSuccess === true) {
                                    unset($messages[$key]);
                                }
                            }
                        }
                    } else {
                        usleep(Config::get('config.empty-queue-sleep-time-ms') * 1000);
                    }

                } catch (\RuntimeException $e) {
                    Logger::info($e->getMessage());
                }

                usleep($readQueue->getSleepTimeMs() * $readQueue->getThrottle() * 1000);
            }
        );
    }
}

