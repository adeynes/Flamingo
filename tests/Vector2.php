<?php
declare(strict_types=1);

namespace adeynes\Flamingo\test;

class Vector2
{

    private $x;

    private $y;

    public function __construct(float $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function distance(Vector2 $other): float {
        return sqrt(($this->getX() - $other->getX())**2 + ($this->getY() - $other->getY())**2);
    }

}