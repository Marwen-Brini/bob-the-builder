<?php

namespace Bob\Contracts;

interface ExpressionInterface
{
    public function getValue();
    public function __toString(): string;
}