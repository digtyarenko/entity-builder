<?php declare(strict_types=1);

namespace EntityBuilder;

use EntityBuilder\Common\Mode;
use EntityBuilder\Exception\InvalidEntityClassException;
use EntityBuilder\Utils\Utils;

/**
 * Class EntityBuilder
 * @package EntityBuilder
 */
class EntityBuilder
{
    private const NO_TYPE = 'NO_TYPE';

    private array $allowedEntities;
    private bool $allowStdClass;
    private int $mode;
    private array $propertiesCache;
    private \Closure $customAction;

    /**
     * @param array $allowedEntities
     * @param bool $allowStdClass
     */
    public function __construct(array $allowedEntities = [], bool $allowStdClass = false)
    {
        $this->allowedEntities = $allowedEntities;
        $this->allowStdClass = $allowStdClass;
    }

    /**
     * @param string $targetEntityClass
     * @param array $data
     * @return array|mixed|null
     */
    public function build(string $targetEntityClass, array $data)
    {
        $this->detectBuildMode($data);

        switch ($this->mode) {
            case Mode::MODE_ONE_ENTITY:
                try {
                    return $this->buildOneEntity($targetEntityClass, $data);
                } catch (InvalidEntityClassException $e) {
                    return null;
                }
            case Mode::MODE_ARRAY_OF_ENTITIES:
                return $this->buildArrayOfEntities($targetEntityClass, $data);
        }

        return null;
    }

    /**
     * @param string $targetEntityClass
     * @param array $oneEntity
     * @return mixed|null
     * @throws InvalidEntityClassException
     */
    public function buildOneEntity(string $targetEntityClass, array $oneEntity)
    {
        if (!$this->isValidEntityClass($targetEntityClass)) {
            throw new InvalidEntityClassException("Invalid entity '{$targetEntityClass}'");
        }

        if (empty($oneEntity)) {
            return null;
        }

        $entity = new $targetEntityClass();
        $entityProperties = $this->getEntityProperties($entity);

        foreach ($entityProperties as $propertyName => $propertyType) {
            if (array_key_exists($propertyName, $oneEntity)) {
                $value = $oneEntity[$propertyName];

                if (null === $value) {
                    unset($oneEntity[$propertyName]);
                    continue;
                }

                if (self::NO_TYPE === $propertyType) {
                    $entity->{$propertyName} = $value;
                    unset($oneEntity[$propertyName]);
                    continue;
                }

                if (class_exists($propertyType)) {
                    $this->fillProperty($entity, $propertyName, $propertyType, $value);
                    unset($oneEntity[$propertyName]);
                    continue;
                }

                $value = Utils::convertValueType($propertyType, $value);

                $entity->{$propertyName} = $value;
                unset($oneEntity[$propertyName]);
            }
        }

        if (!empty($oneEntity)) {
            foreach ($oneEntity as $propertyKey => $propertyName) {
                $entity->{$propertyKey} = $propertyName;
            }
        }

        return $entity;
    }

    /**
     * @param string $targetEntityClass
     * @param array $arrayOfEntities
     * @return array
     */
    public function buildArrayOfEntities(string $targetEntityClass, array $arrayOfEntities): array
    {
        $result = [];

        do {
            $oneEntity = array_shift($arrayOfEntities);

            try {
                $oneEntity = $this->buildOneEntity($targetEntityClass, $oneEntity);

                if (null === $oneEntity) {
                    continue;
                }

                $result[] = $oneEntity;
            } catch (InvalidEntityClassException $e) {
                continue;
            }
        } while (count($arrayOfEntities));

        return $result;
    }

    public function customFillProperty(\Closure $customAction): EntityBuilder
    {
        $this->customAction = $customAction;
        return $this;
    }

    /**
     * @param object $entity
     * @param string $propertyName
     * @param string $targetEntityClass
     * @param array $value
     */
    private function fillProperty(object $entity, string $propertyName, string $targetEntityClass, array $value): void
    {
        if (!empty($this->customAction)) {
            $customAction = $this->getCustomAction();
            $customAction->call($this, $entity, $propertyName, $targetEntityClass, $value);
        }

        if (isset($entity->{$propertyName})) {
            return;
        }

        try {
            $oneEntity = $this->buildOneEntity($targetEntityClass, $value);
        } catch (InvalidEntityClassException $e) {
            return;
        }

        if (null === $oneEntity) {
            return;
        }

        $entity->{$propertyName} = $oneEntity;
    }

    /**
     * @param object $entity
     * @return array
     */
    private function getEntityProperties(object $entity): array
    {
        if (empty($this->propertiesCache)) {
            $this->propertiesCache = [];
        }

        $class = get_class($entity);

        if (!array_key_exists($class, $this->propertiesCache)) {
            try {
                $properties = (new \ReflectionObject($entity))->getProperties();
                $this->propertiesCache[$class] = [];

                foreach ($properties as $property) {
                    $propertyName = $property->getName();

                    if ($property->hasType()) {
                        $this->propertiesCache[$class][$propertyName] = $property->getType()->getName();
                        continue;
                    }

                    $this->propertiesCache[$class][$propertyName] = self::NO_TYPE;
                }
            } catch (\Throwable $e) {
                return [];
            }
        }

        return $this->propertiesCache[$class];
    }

    /**
     * @param string $entityClass
     * @return bool
     */
    private function isValidEntityClass(string $entityClass): bool
    {
        if (!class_exists($entityClass)) {
            return false;
        }

        if (!empty($this->allowedEntities)) {
            $notIsAllowedEntity = !in_array($entityClass, $this->allowedEntities, true);
            $notIsSubclassOfAllowedEntity = empty(array_filter($this->allowedEntities, function ($allowedEntity) use($entityClass) {
                return is_subclass_of($entityClass, $allowedEntity);
            }));

            if ($notIsAllowedEntity && $notIsSubclassOfAllowedEntity && !$this->allowStdClass) {
                return false;
            }
        }

        if ($this->allowStdClass && $entityClass !== \stdClass::class) {
            return false;
        }

        return true;
    }

    /**
     * @param array $data
     */
    private function detectBuildMode(array $data): void
    {
        if (empty(array_filter($data, function ($item) {
            return !is_array($item);
        }))) {
            $this->mode = Mode::MODE_ARRAY_OF_ENTITIES;
            return;
        }

        $this->mode = Mode::MODE_ONE_ENTITY;
    }

    /**
     * @return \Closure
     */
    private function getCustomAction(): \Closure
    {
        return $this->customAction;
    }
}
