<?php
declare(strict_types=1);

namespace rabbit\log;

use Psr\Http\Message\ServerRequestInterface;
use rabbit\core\Context;
use rabbit\httpserver\IPHelper;
use rabbit\server\AttributeEnum;

/**
 * Class RegisterTemplateHandler
 * @package rabbit\log
 */
class RegisterTemplateHandler implements TemplateInterface
{
    /** @var array */
    private $possibleStyles = [];
    /** @var array */
    private $htmlColors = [];

    /**
     * RegisterTemplateHandler constructor.
     */
    public function __construct()
    {
        $this->possibleStyles = (new ConsoleColor())->getPossibleStyles();
        $this->htmlColors = HtmlColor::getPossibleColors();
    }

    /**
     * @return array
     */
    public function handle(): array
    {
        if (($request = Context::get(Logger::CONTEXT_KEY)) === null) {
            /** @var ServerRequestInterface $serverRequest */
            if (($serverRequest = Context::get('request')) !== null) {
                $uri = $serverRequest->getUri();
                $requestId = $serverRequest->getAttribute(AttributeEnum::REQUESTID_ATTRIBUTE);
                !$requestId && $requestId = uniqid();
                $request = array_filter([
                    '%Q' => $requestId,
                    '%R' => $uri->getPath(),
                    '%m' => $serverRequest->getMethod(),
                    '%I' => IPHelper::getClientIp($serverRequest),
                    '%c' => [
                        $this->possibleStyles[rand(0, count($this->possibleStyles) - 1)],
                        $this->htmlColors[rand(0, count($this->htmlColors) - 1)]
                    ]
                ]);
            } else {
                $request = array_filter([
                    '%Q' => uniqid(),
                    '%c' => [
                        $this->possibleStyles[rand(0, count($this->possibleStyles) - 1)],
                        $this->htmlColors[rand(0, count($this->htmlColors) - 1)]
                    ]
                ]);
            }
            Context::set(Logger::CONTEXT_KEY, $request);
        }
        return $request;
    }
}
