<?php

namespace SenseiTarzan\ExtraEvent\Component;

use pocketmine\event\Event;
use pocketmine\plugin\PluginBase;
use ReflectionClass;
use ReflectionException;
use SenseiTarzan\ExtraEvent\Class\EventAttribute;

class EventLoader
{

    public static function loadEventWithClass(PluginBase $plugin, object|string $class): void
    {
        try {
            $reflectClass = new ReflectionClass($class);
        } catch (ReflectionException) {
            $plugin->getLogger()->warning($class . "no existe ");
            return;
        }
        try {
            $instance = is_object($class) ? $class : $reflectClass->newInstanceWithoutConstructor();

        } catch (ReflectionException) {
            $plugin->getLogger()->warning($class . "can't create new instance without constructor");
            return;
        }
        foreach ($reflectClass->getMethods() as $method) {
            $attributes = $method->getAttributes(EventAttribute::class);
            if (empty($attributes)) continue;
            $attribute = array_filter($attributes, fn(mixed $attribute) => $attribute instanceof EventAttribute);
            $attribute = ($attribute[array_key_first($attribute)] ?? null)?->newInstance();
            if (!($attribute instanceof EventAttribute)) continue;
            $eventType = self::getEventsHandledBy($method);
            if ($eventType === null) continue;
            $plugin->getServer()->getPluginManager()->registerEvent($eventType, $method->getClosure($instance), $attribute->getPriority(), $plugin, $attribute->isHandleCancelled());

        }
        unset($instance);

    }

    private static function getEventsHandledBy(\ReflectionMethod $method): ?string
    {
        if ($method->isStatic() || empty($method->getAttributes(EventAttribute::class))) {
            return null;
        }


        $parameters = $method->getParameters();
        if (count($parameters) !== 1) {
            return null;
        }

        $paramType = $parameters[0]->getType();
        //isBuiltin() returns false for builtin classes ..................
        if (!$paramType instanceof \ReflectionNamedType || $paramType->isBuiltin()) {
            return null;
        }

        /** @phpstan-var class-string $paramClass */
        $paramClass = $paramType->getName();
        $eventClass = new \ReflectionClass($paramClass);
        if (!$eventClass->isSubclassOf(Event::class)) {
            return null;
        }

        /** @var \ReflectionClass<Event> $eventClass */
        return $eventClass->getName();
    }

}