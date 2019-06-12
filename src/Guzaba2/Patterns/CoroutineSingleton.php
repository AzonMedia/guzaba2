<?php


namespace Guzaba2\Patterns;

/**
 * Class CoroutineSingleton
 * A singleton within the current coroutine.
 * Multiple CoroutineSingletons can exist in a single Request
 * If a singleton within the Request is needed please use RequestSingleton class
 * @package Guzaba2\Patterns
 */
class CoroutineSingleton extends Singleton
{

}