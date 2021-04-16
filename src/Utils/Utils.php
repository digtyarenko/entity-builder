<?php declare(strict_types=1);

namespace EntityBuilder\Utils;

/**
 * Class Utils
 * @package EntityBuilder\Utils
 */
class Utils
{
    /**
     * @param string $targetType
     * @param $value
     * @return array|bool|float|int|mixed|string
     */
    public static function convertValueType(string $targetType, $value)
    {
        switch ($targetType) {
            case 'bool':
                return (bool) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'array':
                return (array) $value;
            case 'string':
                return (string) $value;
            default:
                return $value;
        }
    }
}
