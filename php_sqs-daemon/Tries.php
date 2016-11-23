mespace Ocono\Daemon\Helper;

/**
 * Class Tries
 * @package Ocono\Daemon\Helper
 */
class Tries
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * @var int
     */
    private $nrTries;

    /**
     * @var string
     */
    private $errorMessage;

    /**
     * Retry constructor.
     * @param callable $callback
     * @param int $nrTries
     * @param string $errorMessage
     */
    public function __construct(callable $callback, $nrTries, $errorMessage)
    {
        $this->nrTries = $nrTries;
        $this->callback = $callback;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Runs retries on callback
     * @return mixed
     * @throws \RuntimeException
     */
    public function getResult()
    {
        while ($this->nrTries-- > 0) {
            if ($result = call_user_func($this->callback)) {
                return $result;
            }
        }

        throw new \RuntimeException($this->errorMessage);
    }
}

