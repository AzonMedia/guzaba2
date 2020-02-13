<?php
declare(strict_types=1);

namespace Guzaba2\Translator;

use Guzaba2\Kernel\Kernel;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;

/**
 * Class Translator
 * @package Guzaba2\Translator
 */
abstract class Translator extends \Azonmedia\Translator\Translator
{
    /**
     * If the provided $target_language is not withing the supported ones only a notice is emitted and the target language is not changed.
     * @overrides
     * @param string $target_language
     * @param RequestInterface|null $Request
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Azonmedia\Exceptions\RunTimeException
     */
    public static function set_target_language(string $target_language, ?RequestInterface $Request = NULL): void
    {
        if ($target_language) {
            $supported_languages = self::get_supported_languages();
            if (in_array($target_language, $supported_languages, TRUE)) {
                parent::set_target_language($target_language);
            } else {
                $message = sprintf(self::_('An unsupported language "%s" is requested with route %s. The supported languges are "%s".'), $target_language, $Request->getUri()->getPath(), implode(', ', $supported_languages));
                Kernel::log($message, LogLevel::NOTICE);
            }

        } else {
            //ignore this
        }
    }
}
