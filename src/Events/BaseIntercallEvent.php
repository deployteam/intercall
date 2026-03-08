<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Events;

use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Exceptions\Events\InvalidEventException;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @template TPayload of array<string, mixed>
 */
abstract class BaseIntercallEvent implements IntercallEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(protected array $payload = [])
    {
        $this->hydrateFromPayload($this->payload);
    }

    /**
     * @return TPayload
     */
    public function getPayload(): array
    {
        if ($this->usesTypedProperties()) {
            /** @var TPayload */
            return $this->generatePayloadFromProperties();
        }

        /** @var TPayload */
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $payload = $data['payload'] ?? [];

        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new static(); // @phpstan-ignore-line new.static,return.type
        }

        $params = $constructor->getParameters();

        $typedParams = array_filter($params, function (ReflectionParameter $param): bool {
            return $param->getName() !== 'payload';
        });

        if (count($typedParams) > 0) {
            $args = [];
            foreach ($params as $param) {
                $name = $param->getName();
                if ($name === 'payload') {
                    $args[] = $payload;
                } elseif (array_key_exists($name, $payload)) {
                    $args[] = $payload[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $args[] = null;
                } else {
                    throw InvalidEventException::missingRequiredParameter(static::class, $name);
                }
            }
            return new static(...$args); // @phpstan-ignore-line new.static,return.type
        }

        return new static($payload); // @phpstan-ignore-line new.static,return.type
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_name' => $this->getEventName(),
            'payload' => $this->getPayload(),
            'event_class' => static::class,
        ];
    }

    protected function usesTypedProperties(): bool
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $properties = array_filter($properties, function (ReflectionProperty $prop): bool {
            return $prop->getDeclaringClass()->getName() !== self::class
                && $prop->getDeclaringClass()->getName() !== BaseIntercallEvent::class;
        });

        return count($properties) > 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function hydrateFromPayload(array $payload): void
    {
        if (!$this->usesTypedProperties() || $payload === []) {
            return;
        }

        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->getName() === self::class
                || $property->getDeclaringClass()->getName() === BaseIntercallEvent::class) {
                continue;
            }

            $name = $property->getName();
            if (array_key_exists($name, $payload)) {
                $property->setValue($this, $payload[$name]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function generatePayloadFromProperties(): array
    {
        $payload = [];
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->getName() === self::class
                || $property->getDeclaringClass()->getName() === BaseIntercallEvent::class) {
                continue;
            }

            $name = $property->getName();
            if ($property->isInitialized($this)) {
                $payload[$name] = $property->getValue($this);
            }
        }

        return $payload;
    }
}
