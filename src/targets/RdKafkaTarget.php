<?php
declare(strict_types=1);

namespace rabbit\log\targets;

use Co\System;
use rabbit\helper\ArrayHelper;
use Rabbit\Rdkafka\KafkaManager;
use RdKafka\Producer;
use RdKafka\ProducerTopic;

/**
 * Class RdKafka
 * @package rabbit\log\targets
 */
class RdKafkaTarget extends AbstractTarget
{
    /** @var array */
    private $template = [
        ['datetime', 'timespan'],
        ['level', 'string'],
        ['request_uri', 'string'],
        ['request_method', 'string'],
        ['clientip', 'string'],
        ['requestid', 'string'],
        ['filename', 'string'],
        ['memoryusage', 'int'],
        ['message', 'string']
    ];
    /** @var Producer */
    private $producer;
    /** @var string */
    private $key = 'kafka';
    /** @var ProducerTopic */
    private $topic = 'seaslog';
    /** @var int */
    private $ack = 0;
    /** @var float|int */
    private $autoCommit = 1 * 1000;

    /**
     * KafkaTarget constructor.
     * @param Client $client
     */
    public function __construct(string $producer, string $key = 'kafka')
    {
        $this->producer = $producer;
        $this->key = $key;
    }

    public function init()
    {
        /** @var KafkaManager $kafka */
        $kafka = getDI($this->key);
        $this->topic = $kafka->getProducerTopic($this->producer, $this->topic, [
            'acks' => $this->ack,
            'auto.commit.interval.ms' => $this->autoCommit
        ]);
    }


    /**
     * @param array $messages
     */
    public function export(array $messages): void
    {
        foreach ($messages as $module => $message) {
            foreach ($message as $msg) {
                if (is_string($msg)) {
                    switch (ini_get('seaslog.appender')) {
                        case '2':
                        case '3':
                            $msg = trim(substr($msg, StringHelper::str_n_pos($msg, ' ', 6)));
                            break;
                        case '1':
                        default:
                            $fileName = basename($module);
                            $module = substr($fileName, 0, strrpos($fileName, '_'));
                    }
                    $msg = explode($this->split, trim($msg));
                } else {
                    ArrayHelper::remove($msg, '%c');
                }
                if (!empty($this->levelList) && !in_array($msg[$this->levelIndex], $this->levelList)) {
                    continue;
                }
                $log = [
                    'appname' => $module,
                ];
                foreach ($msg as $index => $value) {
                    [$name, $type] = $this->template[$index];
                    switch ($type) {
                        case "string":
                            $log[$name] = trim($value);
                            break;
                        case "int":
                            $log[$name] = (int)$value;
                            break;
                        default:
                            $log[$name] = trim($value);
                    }
                }
                while (!$this->topic instanceof ProducerTopic) {
                    System::sleep(0.001);
                }
                $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($log));
            }
        }
    }
}