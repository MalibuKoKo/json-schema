<?php

declare(strict_types=1);

/*
 * This file has been auto generated by Jane,
 *
 * Do no edit it directly.
 */

namespace Jane\JsonSchema\Tests\Expected\Model;

class Otherchildtype
{
    /**
     * @var string
     */
    protected $inheritedProperty;
    /**
     * @var string
     */
    protected $childProperty;

    /**
     * @return string
     */
    public function getInheritedProperty(): ?string
    {
        return $this->inheritedProperty;
    }

    /**
     * @param string $inheritedProperty
     *
     * @return self
     */
    public function setInheritedProperty(?string $inheritedProperty): self
    {
        $this->inheritedProperty = $inheritedProperty;

        return $this;
    }

    /**
     * @return string
     */
    public function getChildProperty(): ?string
    {
        return $this->childProperty;
    }

    /**
     * @param string $childProperty
     *
     * @return self
     */
    public function setChildProperty(?string $childProperty): self
    {
        $this->childProperty = $childProperty;

        return $this;
    }
}