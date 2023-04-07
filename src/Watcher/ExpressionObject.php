<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Watcher;

use Tsiura\PromiseWatcher\EvaluatedObjectInterface;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

readonly class ExpressionObject implements EvaluatedObjectInterface
{
    private Expression $expression;

    public function __construct(
        string $className,
        array $additionalData = [],
    ) {
        $andX = [
            Expr::isInstanceOf($className),
        ];

        if (!empty($additionalData)) {
            foreach ($additionalData as $key => $value) {
                $andX[] = Expr::property($key, Expr::equals($value));
            }
        }

        $this->expression = Expr::andX($andX);
    }

    public function evaluate(mixed $object): bool
    {
        return $this->expression->evaluate($object);
    }

    public function __toString(): string
    {
        return $this->expression->toString();
    }
}
